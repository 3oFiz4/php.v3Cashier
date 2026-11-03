<?php

namespace App\Libraries;

use PDO;

// alokasikan pengurangan stok berdasarkan batch tanggal kadaluarsa terdekat (fefo)
class FefoAllocator
{
    // kurangi stok batch secara berurutan dari tanggal kadaluarsa paling awal
    public static function deductFefoBatches(PDO $db, int $idBarang, int $qtyNeeded): ?int
    {
        $stmt = $db->prepare(
            "SELECT id_batch, stok_tersedia, tanggal_kadaluarsa
             FROM batch_barang
             WHERE id_barang = ? AND stok_tersedia > 0 AND status_batch IN ('Active', 'Near_Expiry')
             ORDER BY tanggal_kadaluarsa ASC, id_batch ASC
             FOR UPDATE"
        );
        $stmt->execute([$idBarang]);
        $batches = $stmt->fetchAll();

        $remainingToDeduct = $qtyNeeded;
        $primaryBatchId    = null;

        $updateBatch = $db->prepare(
            "UPDATE batch_barang
             SET stok_tersedia = ?, status_batch = CASE WHEN ? <= 0 THEN 'Depleted' ELSE status_batch END
             WHERE id_batch = ?"
        );

        foreach ($batches as $batch) {
            if ($remainingToDeduct <= 0) {
                break;
            }

            $avail = (int) $batch['stok_tersedia'];
            $take  = min($avail, $remainingToDeduct);
            $left  = $avail - $take;

            if ($primaryBatchId === null) {
                $primaryBatchId = (int) $batch['id_batch'];
            }

            $updateBatch->execute([$left, $left, (int) $batch['id_batch']]);
            $remainingToDeduct -= $take;
        }

        return $primaryBatchId;
    }

    // segarkan status batch yang mendekati atau melewati tanggal kadaluarsa
    public static function refreshExpiryStatuses(PDO $db, int $warningDays = 3): void
    {
        $db->exec("UPDATE batch_barang SET status_batch = 'Depleted' WHERE stok_tersedia <= 0");
        $db->exec("UPDATE batch_barang SET status_batch = 'Expired' WHERE stok_tersedia > 0 AND tanggal_kadaluarsa < CURDATE()");
        $stmt = $db->prepare(
            "UPDATE batch_barang
             SET status_batch = 'Near_Expiry'
             WHERE stok_tersedia > 0
               AND tanggal_kadaluarsa >= CURDATE()
               AND tanggal_kadaluarsa <= DATE_ADD(CURDATE(), INTERVAL ? DAY)"
        );
        $stmt->execute([$warningDays]);
    }
}
