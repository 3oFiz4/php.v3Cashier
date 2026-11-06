<?php

namespace App\Controllers;

use App\Filters\AuthFilter;
use App\Models\TransaksiModel;
use App\Models\ShiftRekonModel;
use App\Libraries\AuditLogger;
use App\Libraries\BackupService;
use Config\Database;
use Exception;

// kelola operasional po pemasok, stok opname, refund transaksi, log audit, dan backup database
class OperasionalController extends BaseController
{
    // tampilkan daftar pemasok distributor
    public function listSuppliers(): void
    {
        AuthFilter::enforce(['admin'], true);
        $db   = Database::connectPdo();
        $stmt = $db->query("SELECT * FROM pemasok ORDER BY nama_pemasok ASC");
        $this->jsonResponse(['suppliers' => $stmt->fetchAll()]);
    }

    // tambahkan pemasok baru
    public function createSupplier(): void
    {
        AuthFilter::enforce(['admin'], true);
        $in          = $this->getJsonInput();
        $kodePemasok = trim((string) ($in['kode_pemasok'] ?? ('SUP-' . random_int(100, 999))));
        $namaPemasok = trim((string) ($in['nama_pemasok'] ?? ''));
        $kontak      = trim((string) ($in['kontak_person'] ?? ''));
        $telepon     = trim((string) ($in['no_telepon'] ?? ''));
        $alamat      = trim((string) ($in['alamat'] ?? ''));

        if ($namaPemasok === '') {
            $this->jsonResponse(['error' => 'nama pemasok wajib diisi'], 400);
        }

        $db   = Database::connectPdo();
        $stmt = $db->prepare("INSERT INTO pemasok (kode_pemasok, nama_pemasok, kontak_person, no_telepon, alamat, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$kodePemasok, $namaPemasok, $kontak, $telepon, $alamat]);

        AuditLogger::log('pemasok', 'CREATE_SUPPLIER', $kodePemasok, null, ['nama_pemasok' => $namaPemasok]);
        $this->jsonResponse(['success' => true, 'message' => 'pemasok berhasil ditambahkan']);
    }

    // tampilkan riwayat faktur pembelian stok masuk (purchase orders)
    public function listPurchaseOrders(): void
    {
        AuthFilter::enforce(['admin'], true);
        $db   = Database::connectPdo();
        $stmt = $db->query(
            "SELECT po.*, p.nama_pemasok, a.nama_lengkap AS nama_admin
             FROM pembelian_stok po
             JOIN pemasok p ON po.id_pemasok = p.id_pemasok
             JOIN admin a ON po.id_admin = a.id_admin
             ORDER BY po.tanggal_pembelian DESC"
        );
        $orders = $stmt->fetchAll();

        $detailStmt = $db->prepare(
            "SELECT d.*, b.nama_barang
             FROM detail_pembelian_stok d
             JOIN barang b ON d.id_barang = b.id_barang
             WHERE d.id_pembelian = ?"
        );
        foreach ($orders as &$po) {
            $detailStmt->execute([(int) $po['id_pembelian']]);
            $po['items'] = $detailStmt->fetchAll();
        }

        $this->jsonResponse(['purchase_orders' => $orders]);
    }

    // catat pengiriman stok masuk dari pemasok dan tambah stok produk serta batch fefo otomatis
    public function createPurchaseOrder(): void
    {
        $user = AuthFilter::enforce(['admin'], true);
        $in   = $this->getJsonInput();

        $noFaktur     = trim((string) ($in['no_faktur_masuk'] ?? ('PO-' . date('YmdHis'))));
        $idPemasok    = (int) ($in['id_pemasok'] ?? 0);
        $idBarang     = (int) ($in['id_barang'] ?? 0);
        $jmlKemasan   = max(1, (int) ($in['jumlah_kemasan'] ?? 1));
        $isiKemasan   = max(1, (int) ($in['unit_per_kemasan'] ?? 24));
        $hargaSatuan  = (float) ($in['harga_beli_satuan'] ?? 0);
        $nomorBatch   = trim((string) ($in['nomor_batch'] ?? ('BATCH-' . date('ymd') . random_int(10, 99))));
        $tglExp       = (string) ($in['tanggal_kadaluarsa'] ?? date('Y-m-d', strtotime('+180 days')));
        $catatan      = trim((string) ($in['catatan'] ?? 'Penerimaan faktur stok distributor'));

        if ($idPemasok <= 0 || $idBarang <= 0) {
            $this->jsonResponse(['error' => 'pemasok dan barang wajib dipilih'], 400);
        }

        $totalUnit  = $jmlKemasan * $isiKemasan;
        $totalBiaya = $totalUnit * $hargaSatuan;

        $db = Database::connectPdo();
        try {
            $db->beginTransaction();

            $stmtPo = $db->prepare(
                "INSERT INTO pembelian_stok (no_faktur_masuk, id_pemasok, id_admin, tanggal_pembelian, total_unit_masuk, total_biaya, catatan)
                 VALUES (?, ?, ?, NOW(), ?, ?, ?)"
            );
            $stmtPo->execute([$noFaktur, $idPemasok, $user['id'], $totalUnit, $totalBiaya, $catatan]);
            $idPo = (int) $db->lastInsertId();

            $stmtDet = $db->prepare(
                "INSERT INTO detail_pembelian_stok
                 (id_pembelian, id_barang, jumlah_kemasan, unit_per_kemasan, total_unit, harga_beli_satuan, subtotal_biaya, nomor_batch, tanggal_kadaluarsa)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmtDet->execute([$idPo, $idBarang, $jmlKemasan, $isiKemasan, $totalUnit, $hargaSatuan, $totalBiaya, $nomorBatch, $tglExp]);

            $updBarang = $db->prepare("UPDATE barang SET stok = stok + ?, harga_beli = ?, updated_at = NOW() WHERE id_barang = ?");
            $updBarang->execute([$totalUnit, $hargaSatuan, $idBarang]);

            $insBatch = $db->prepare(
                "INSERT INTO batch_barang (id_barang, nomor_batch, tanggal_masuk, tanggal_kadaluarsa, stok_awal, stok_tersedia, harga_beli_batch, status_batch, created_at)
                 VALUES (?, ?, CURDATE(), ?, ?, ?, ?, 'Active', NOW())"
            );
            $insBatch->execute([$idBarang, $nomorBatch, $tglExp, $totalUnit, $totalUnit, $hargaSatuan]);

            $db->commit();

            AuditLogger::log('pembelian_stok', 'RECEIVE_PO_STOCK', $noFaktur, null, [
                'id_barang'   => $idBarang,
                'total_unit'  => $totalUnit,
                'total_biaya' => $totalBiaya,
            ]);

            $this->jsonResponse([
                'success'    => true,
                'total_unit' => $totalUnit,
                'message'    => "faktur {$noFaktur} berhasil menambah {$totalUnit} unit ke stok aktif",
            ]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    // tampilkan daftar laporan stok opname dan selisih fisik
    public function listStockOpname(): void
    {
        AuthFilter::enforce(['admin'], true);
        $db   = Database::connectPdo();
        $stmt = $db->query(
            "SELECT o.*, b.nama_barang, b.barcode, a.nama_lengkap AS nama_admin
             FROM stok_opname o
             JOIN barang b ON o.id_barang = b.id_barang
             JOIN admin a ON o.id_admin = a.id_admin
             ORDER BY o.tanggal_opname DESC"
        );
        $this->jsonResponse(['opname_records' => $stmt->fetchAll()]);
    }

    // rekam hasil perhitungan stok fisik rak dan sesuaikan saldo database
    public function createStockOpname(): void
    {
        $user = AuthFilter::enforce(['admin'], true);
        $in   = $this->getJsonInput();

        $idBarang  = (int) ($in['id_barang'] ?? 0);
        $stokFisik = (int) ($in['stok_fisik'] ?? 0);
        $alasan    = trim((string) ($in['alasan_selisih'] ?? 'Penyesuaian stok opname berkala'));

        $db   = Database::connectPdo();
        $stmt = $db->prepare("SELECT * FROM barang WHERE id_barang = ?");
        $stmt->execute([$idBarang]);
        $barang = $stmt->fetch();

        if (!$barang) {
            $this->jsonResponse(['error' => 'barang tidak ditemukan'], 404);
        }

        $stokSistem     = (int) $barang['stok'];
        $selisihUnit    = $stokFisik - $stokSistem;
        $nominalSelisih = $selisihUnit * (float) $barang['harga_beli'];
        $kodeOpname     = 'OPN-' . date('YmdHis') . '-' . random_int(10, 99);

        $db->beginTransaction();
        $ins = $db->prepare(
            "INSERT INTO stok_opname (kode_opname, id_admin, id_barang, stok_sistem, stok_fisik, selisih_unit, nominal_selisih, alasan_selisih, disesuaikan_ke_stok, tanggal_opname)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())"
        );
        $ins->execute([$kodeOpname, $user['id'], $idBarang, $stokSistem, $stokFisik, $selisihUnit, $nominalSelisih, $alasan]);

        $upd = $db->prepare("UPDATE barang SET stok = ?, updated_at = NOW() WHERE id_barang = ?");
        $upd->execute([$stokFisik, $idBarang]);
        $db->commit();

        AuditLogger::log('stok_opname', 'RECORD_OPNAME', $kodeOpname, [
            'stok_sistem' => $stokSistem,
        ], [
            'stok_fisik'   => $stokFisik,
            'selisih_unit' => $selisihUnit,
        ]);

        $this->jsonResponse([
            'success'      => true,
            'selisih_unit' => $selisihUnit,
            'message'      => "stok opname tercatat (selisih: {$selisihUnit} unit)",
        ]);
    }

    // tampilkan seluruh transaksi beserta riwayat otorisasi void
    public function listAllTransactions(): void
    {
        AuthFilter::enforce(['admin'], true);
        $txnModel = new TransaksiModel();
        $db       = Database::connectPdo();
        $voids    = $db->query(
            "SELECT v.*, k.nama_lengkap AS nama_kasir, a.nama_lengkap AS nama_supervisor
             FROM otorisasi_void v
             JOIN kasir k ON v.id_kasir = k.id_kasir
             JOIN admin a ON v.id_admin_supervisor = a.id_admin
             ORDER BY v.waktu_otorisasi DESC LIMIT 50"
        )->fetchAll();

        $this->jsonResponse([
            'transactions' => $txnModel->getTransactions(null, 200),
            'void_logs'    => $voids,
        ]);
    }

    // proses pembatalan/refund struk transaksi dengan pengembalian stok otomatis
    public function refundTransaction(): void
    {
        $user        = AuthFilter::enforce(['admin'], true);
        $in          = $this->getJsonInput();
        $idTransaksi = (int) ($in['id_transaksi'] ?? 0);
        $alasan      = trim((string) ($in['alasan'] ?? 'Refund transaksi atas persetujuan supervisor'));

        $db   = Database::connectPdo();
        $stmt = $db->prepare("SELECT * FROM transaksi WHERE id_transaksi = ? LIMIT 1");
        $stmt->execute([$idTransaksi]);
        $txn = $stmt->fetch();

        if (!$txn || $txn['status_transaksi'] !== 'Completed') {
            $this->jsonResponse(['error' => 'transaksi tidak ditemukan atau sudah dibatalkan'], 400);
        }

        $db->beginTransaction();
        $updTxn = $db->prepare("UPDATE transaksi SET status_transaksi = 'Refunded' WHERE id_transaksi = ?");
        $updTxn->execute([$idTransaksi]);

        $itemsStmt = $db->prepare("SELECT * FROM detail_transaksi WHERE id_transaksi = ?");
        $itemsStmt->execute([$idTransaksi]);
        $restoreStock = $db->prepare("UPDATE barang SET stok = stok + ?, updated_at = NOW() WHERE id_barang = ?");

        foreach ($itemsStmt->fetchAll() as $item) {
            $restoreStock->execute([(int) $item['jumlah'], (int) $item['id_barang']]);
        }

        $insVoid = $db->prepare(
            "INSERT INTO otorisasi_void (id_kasir, id_admin_supervisor, id_transaksi, jenis_void, nominal_terdampak, alasan_void, waktu_otorisasi)
             VALUES (?, ?, ?, 'Refund_Receipt', ?, ?, NOW())"
        );
        $insVoid->execute([(int) $txn['id_kasir'], $user['id'], $idTransaksi, (float) $txn['total_harga'], $alasan]);
        $db->commit();

        AuditLogger::log('transaksi', 'REFUND_RECEIPT', $txn['no_struk'], ['status' => 'Completed'], ['status' => 'Refunded', 'alasan' => $alasan]);

        $this->jsonResponse(['success' => true, 'message' => "struk {$txn['no_struk']} berhasil direfund dan stok dikembalikan"]);
    }

    // tampilkan daftar rekonsiliasi kas shift kasir
    public function listShifts(): void
    {
        AuthFilter::enforce(['admin'], true);
        $shiftModel = new ShiftRekonModel();
        $this->jsonResponse(['shifts' => $shiftModel->getAllShifts()]);
    }

    // tampilkan catatan jejak audit sistem imutabel
    public function listAuditLogs(): void
    {
        AuthFilter::enforce(['admin'], true);
        $db   = Database::connectPdo();
        $stmt = $db->query("SELECT * FROM log_audit ORDER BY waktu_kejadian DESC LIMIT 150");
        $this->jsonResponse(['audit_logs' => $stmt->fetchAll()]);
    }

    // tampilkan daftar riwayat file cadangan database (.sql.gz)
    public function listBackups(): void
    {
        AuthFilter::enforce(['admin'], true);
        $db   = Database::connectPdo();
        $stmt = $db->query("SELECT * FROM riwayat_backup ORDER BY waktu_backup DESC LIMIT 50");
        $this->jsonResponse(['backups' => $stmt->fetchAll()]);
    }

    // jalankan pencadangan database .sql.gz secara langsung dari dashboard admin
    public function triggerBackup(): void
    {
        $user    = AuthFilter::enforce(['admin'], true);
        $service = new BackupService();
        $result  = $service->createCompressedBackup('Manual_Admin', $user['id']);
        $this->jsonResponse($result);
    }
}
