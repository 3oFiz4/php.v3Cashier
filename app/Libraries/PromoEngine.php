<?php

namespace App\Libraries;

use Config\Database;

// hitung potongan harga otomatis dan harga paket bundling pada keranjang kasir
class PromoEngine
{
    // evaluasi daftar item belanja terhadap aturan promosi aktif hari ini
    public static function evaluateCart(array $items): array
    {
        $db    = Database::connectPdo();
        $today = date('Y-m-d');

        $stmt = $db->prepare(
            "SELECT * FROM promosi_bundling
             WHERE status_aktif = 1 AND tanggal_mulai <= ? AND tanggal_akhir >= ?"
        );
        $stmt->execute([$today, $today]);
        $promosByProduct = [];
        foreach ($stmt->fetchAll() as $promo) {
            $promosByProduct[(int) $promo['id_barang']][] = $promo;
        }

        $evaluatedItems = [];
        $subtotalAwal   = 0.0;
        $totalDiskon    = 0.0;
        $totalAkhir     = 0.0;
        $totalQty       = 0;

        foreach ($items as $item) {
            $idBarang   = (int) ($item['id_barang'] ?? $item['product_id'] ?? 0);
            $qty        = (int) ($item['jumlah'] ?? $item['quantity'] ?? 0);
            $hargaJual  = (float) ($item['harga_jual'] ?? $item['price'] ?? 0);
            $hargaBeli  = (float) ($item['harga_beli'] ?? 0);
            $namaBarang = (string) ($item['nama_barang'] ?? $item['name'] ?? '');

            $lineGross  = $hargaJual * $qty;
            $lineDiskon = 0.0;
            $labelPromo = null;

            if (isset($promosByProduct[$idBarang])) {
                foreach ($promosByProduct[$idBarang] as $rule) {
                    $minQty = max(1, (int) $rule['min_kuantitas']);
                    if ($qty < $minQty) {
                        continue;
                    }

                    $candidateDiscount = 0.0;
                    if ($rule['tipe_promo'] === 'Bundle_Pair' && (float) $rule['harga_paket'] > 0) {
                        $bundleCount       = intdiv($qty, $minQty);
                        $normalBundlePrice = $hargaJual * $minQty;
                        $savingPerBundle   = max(0.0, $normalBundlePrice - (float) $rule['harga_paket']);
                        $candidateDiscount = $bundleCount * $savingPerBundle;
                    } elseif ($rule['tipe_promo'] === 'Min_Qty_Cut') {
                        $bundleCount       = intdiv($qty, $minQty);
                        $candidateDiscount = $bundleCount * (float) $rule['potongan_rupiah'];
                    } elseif ($rule['tipe_promo'] === 'Percent_Discount') {
                        $candidateDiscount = round($lineGross * ((float) $rule['persen_diskon'] / 100.0), 2);
                    }

                    if ($candidateDiscount > $lineDiskon) {
                        $lineDiskon = min($lineGross, $candidateDiscount);
                        $labelPromo = $rule['nama_promo'];
                    }
                }
            }

            $lineNet = max(0.0, $lineGross - $lineDiskon);

            $subtotalAwal += $lineGross;
            $totalDiskon  += $lineDiskon;
            $totalAkhir   += $lineNet;
            $totalQty     += $qty;

            $evaluatedItems[] = [
                'id_barang'      => $idBarang,
                'nama_barang'    => $namaBarang,
                'harga_beli'     => $hargaBeli,
                'harga_satuan'   => $hargaJual,
                'jumlah'         => $qty,
                'subtotal_kotor' => $lineGross,
                'diskon_item'    => $lineDiskon,
                'label_promo'    => $labelPromo,
                'subtotal'       => $lineNet,
            ];
        }

        return [
            'items'         => $evaluatedItems,
            'total_item'    => $totalQty,
            'subtotal_awal' => round($subtotalAwal, 2),
            'total_diskon'  => round($totalDiskon, 2),
            'total_harga'   => round($totalAkhir, 2),
        ];
    }
}
