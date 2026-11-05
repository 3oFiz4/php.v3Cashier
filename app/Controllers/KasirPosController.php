<?php

namespace App\Controllers;

use App\Filters\AuthFilter;
use App\Models\BarangModel;
use App\Models\JenisBarangModel;
use App\Models\TransaksiModel;
use App\Models\ShiftRekonModel;
use App\Libraries\PromoEngine;
use App\Libraries\FefoAllocator;
use App\Libraries\QrisGenerator;
use App\Libraries\AuditLogger;
use Config\Database;
use Config\PosConfig;
use Exception;

// kelola antarmuka kasir pos, pemindaian barcode, keranjang tunda, qris, dan checkout fefo
class KasirPosController extends BaseController
{
    // tampilkan halaman terminal transaksi kasir pos
    public function index(): void
    {
        $user = AuthFilter::enforce(['kasir', 'admin'], false);
        $this->renderView('kasir/pos', ['user' => $user, 'posConfig' => new PosConfig()]);
    }

    // muat katalog produk aktif, daftar kategori, dan aturan promosi untuk terminal kasir
    public function catalog(): void
    {
        AuthFilter::enforce(['kasir', 'admin'], true);
        $category = $_GET['category'] ?? 'all';
        $search   = $_GET['search'] ?? '';

        $barangModel = new BarangModel();
        $jenisModel  = new JenisBarangModel();
        $db          = Database::connectPdo();

        $promos = $db->query(
            "SELECT p.*, b.nama_barang
             FROM promosi_bundling p
             JOIN barang b ON p.id_barang = b.id_barang
             WHERE p.status_aktif = 1 AND p.tanggal_mulai <= CURDATE() AND p.tanggal_akhir >= CURDATE()"
        )->fetchAll();

        $this->jsonResponse([
            'products'   => $barangModel->getCatalog($category, $search),
            'categories' => $jenisModel->getCategoriesWithCount(),
            'promotions' => $promos,
        ]);
    }

    // cari produk berdasarkan kode barcode dari pemindai usb atau nirkabel
    public function scanBarcode(string $barcode): void
    {
        AuthFilter::enforce(['kasir', 'admin'], true);
        $barangModel = new BarangModel();
        $product     = $barangModel->findByBarcode($barcode);

        if (!$product) {
            $this->jsonResponse(['error' => "produk dengan barcode '{$barcode}' tidak ditemukan"], 404);
        }
        $this->jsonResponse(['success' => true, 'product' => $product]);
    }

    // hitung subtotal per item, diskon bundling otomatis, dan total tagihan keranjang
    public function calculateCart(): void
    {
        AuthFilter::enforce(['kasir', 'admin'], true);
        $input = $this->getJsonInput();
        $items = $input['items'] ?? [];
        $calc  = PromoEngine::evaluateCart($items);
        $this->jsonResponse(['success' => true, 'calculation' => $calc]);
    }

    // hasilkan kode qris dinamis beserta nominal pembayaran non-tunai
    public function generateDynamicQris(): void
    {
        AuthFilter::enforce(['kasir', 'admin'], true);
        $input  = $this->getJsonInput();
        $amount = (float) ($input['amount'] ?? 0);
        if ($amount <= 0) {
            $this->jsonResponse(['error' => 'nominal qris harus lebih besar dari 0'], 400);
        }
        $ref  = 'QRIS-' . date('YmdHis') . '-' . random_int(100, 999);
        $qris = QrisGenerator::generatePayload($amount, $ref);
        $this->jsonResponse(['success' => true, 'qris' => $qris]);
    }

    // proses transaksi penjualan multi-item secara real-time dengan pengurangan stok fefo
    public function checkout(): void
    {
        $user  = AuthFilter::enforce(['kasir', 'admin'], true);
        $input = $this->getJsonInput();
        $items = $input['items'] ?? [];

        if (empty($items)) {
            $this->jsonResponse(['error' => 'keranjang belanja masih kosong'], 400);
        }

        $metodePembayaran = (string) ($input['payment_method'] ?? 'Cash');
        $bayarTunai       = (float) ($input['cash_amount'] ?? $input['payment_amount'] ?? 0);
        $bayarNonTunai    = (float) ($input['non_cash_amount'] ?? 0);
        $referensiBayar   = trim((string) ($input['payment_reference'] ?? ''));

        $db = Database::connectPdo();

        try {
            $db->beginTransaction();

            $lockedProducts = [];
            foreach ($items as $reqItem) {
                $idBarang = (int) ($reqItem['id_barang'] ?? $reqItem['product_id'] ?? 0);
                $qty      = (int) ($reqItem['jumlah'] ?? $reqItem['quantity'] ?? 0);

                if ($idBarang <= 0 || $qty <= 0) {
                    throw new Exception('data item transaksi tidak valid');
                }

                $stmt = $db->prepare("SELECT * FROM barang WHERE id_barang = ? FOR UPDATE");
                $stmt->execute([$idBarang]);
                $prod = $stmt->fetch();

                if (!$prod) {
                    throw new Exception("produk id {$idBarang} tidak ditemukan");
                }
                if ((int) $prod['stok'] < $qty) {
                    throw new Exception("stok '{$prod['nama_barang']}' tidak mencukupi (tersedia: {$prod['stok']}, diminta: {$qty})");
                }

                $lockedProducts[] = [
                    'id_barang'   => (int) $prod['id_barang'],
                    'nama_barang' => $prod['nama_barang'],
                    'harga_beli'  => (float) $prod['harga_beli'],
                    'harga_jual'  => (float) $prod['harga_jual'],
                    'jumlah'      => $qty,
                ];
            }

            $evaluated   = PromoEngine::evaluateCart($lockedProducts);
            $totalHarga  = (float) $evaluated['total_harga'];
            $jumlahBayar = $bayarTunai + $bayarNonTunai;

            if ($jumlahBayar < $totalHarga) {
                throw new Exception("nominal pembayaran kurang dari total tagihan");
            }

            $kembalian   = max(0.0, $jumlahBayar - $totalHarga);
            $idKasir     = $user['role'] === 'kasir' ? $user['id'] : 1;
            $shiftModel  = new ShiftRekonModel();
            $activeShift = $shiftModel->getActiveShift($idKasir);
            $idRekon     = $activeShift ? (int) $activeShift['id_rekon'] : null;

            $posConfig = new PosConfig();
            $noStruk   = $posConfig->receiptPrefix . '-' . date('Ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);

            $insertTxn = $db->prepare(
                "INSERT INTO transaksi
                 (no_struk, tanggal_transaksi, id_kasir, id_rekon, total_item, subtotal_awal, total_diskon, total_harga,
                  metode_pembayaran, bayar_tunai, bayar_non_tunai, referensi_pembayaran, jumlah_bayar, kembalian, status_transaksi)
                 VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Completed')"
            );
            $insertTxn->execute([
                $noStruk,
                $idKasir,
                $idRekon,
                (int) $evaluated['total_item'],
                (float) $evaluated['subtotal_awal'],
                (float) $evaluated['total_diskon'],
                $totalHarga,
                $metodePembayaran,
                $bayarTunai,
                $bayarNonTunai,
                $referensiBayar !== '' ? $referensiBayar : null,
                $jumlahBayar,
                $kembalian,
            ]);
            $idTransaksi = (int) $db->lastInsertId();

            $insertDetail = $db->prepare(
                "INSERT INTO detail_transaksi
                 (id_transaksi, id_barang, id_batch, nama_barang_snapshot, harga_beli_satuan, harga_satuan, jumlah, diskon_item, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $updateMasterStock = $db->prepare(
                "UPDATE barang SET stok = stok - ?, updated_at = NOW() WHERE id_barang = ?"
            );

            foreach ($evaluated['items'] as $line) {
                $batchId = FefoAllocator::deductFefoBatches($db, (int) $line['id_barang'], (int) $line['jumlah']);

                $insertDetail->execute([
                    $idTransaksi,
                    (int) $line['id_barang'],
                    $batchId,
                    $line['nama_barang'],
                    (float) $line['harga_beli'],
                    (float) $line['harga_satuan'],
                    (int) $line['jumlah'],
                    (float) $line['diskon_item'],
                    (float) $line['subtotal'],
                ]);

                $updateMasterStock->execute([(int) $line['jumlah'], (int) $line['id_barang']]);
            }

            if ($idRekon !== null) {
                $netCashSales = max(0.0, $bayarTunai - $kembalian);
                $updShift     = $db->prepare(
                    "UPDATE rekonsiliasi_shift
                     SET penjualan_tunai_sistem = penjualan_tunai_sistem + ?,
                         penjualan_non_tunai_sistem = penjualan_non_tunai_sistem + ?
                     WHERE id_rekon = ?"
                );
                $updShift->execute([$netCashSales, $bayarNonTunai, $idRekon]);
            }

            $db->commit();

            AuditLogger::log('transaksi', 'CHECKOUT_SUCCESS', $noStruk, null, [
                'id_transaksi'      => $idTransaksi,
                'total_harga'       => $totalHarga,
                'metode_pembayaran' => $metodePembayaran,
            ]);

            $txnModel = new TransaksiModel();
            $this->jsonResponse([
                'success'     => true,
                'transaction' => $txnModel->getTransactionWithDetails($idTransaksi),
            ]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    // tampilkan daftar riwayat transaksi yang diproses oleh kasir terkait
    public function myTransactions(): void
    {
        $user     = AuthFilter::enforce(['kasir', 'admin'], true);
        $txnModel = new TransaksiModel();
        $idFilter = $user['role'] === 'kasir' ? $user['id'] : null;
        $this->jsonResponse(['transactions' => $txnModel->getTransactions($idFilter, 50)]);
    }

    // ambil data struk transaksi tertentu untuk dicetak ulang
    public function receipt(int $idTransaksi): void
    {
        AuthFilter::enforce(['kasir', 'admin'], true);
        $txnModel = new TransaksiModel();
        $txn      = $txnModel->getTransactionWithDetails($idTransaksi);
        if (!$txn) {
            $this->jsonResponse(['error' => 'struk transaksi tidak ditemukan'], 404);
        }
        $this->jsonResponse(['success' => true, 'transaction' => $txn]);
    }

    // ambil daftar transaksi tertunda (parked / held carts)
    public function listHeldCarts(): void
    {
        AuthFilter::enforce(['kasir', 'admin'], true);
        $db   = Database::connectPdo();
        $stmt = $db->query(
            "SELECT t.*, k.nama_lengkap AS nama_kasir
             FROM transaksi_tertunda t
             JOIN kasir k ON t.id_kasir = k.id_kasir
             WHERE t.status_tunda = 'Held'
             ORDER BY t.waktu_tunda DESC"
        );
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['items'] = json_decode($r['payload_keranjang'], true) ?: [];
        }
        $this->jsonResponse(['held_carts' => $rows]);
    }

    // simpan sementara (park/hold) keranjang belanja pelanggan aktif
    public function parkCart(): void
    {
        $user  = AuthFilter::enforce(['kasir', 'admin'], true);
        $input = $this->getJsonInput();
        $items = $input['items'] ?? [];
        $label = trim((string) ($input['customer_label'] ?? 'Pelanggan Umum'));

        if (empty($items)) {
            $this->jsonResponse(['error' => 'tidak dapat menunda keranjang kosong'], 400);
        }

        $calc      = PromoEngine::evaluateCart($items);
        $kodeTunda = 'HOLD-' . date('His') . '-' . random_int(10, 99);
        $idKasir   = $user['role'] === 'kasir' ? $user['id'] : 1;

        $db   = Database::connectPdo();
        $stmt = $db->prepare(
            "INSERT INTO transaksi_tertunda (kode_tunda, id_kasir, label_pelanggan, payload_keranjang, subtotal_sementara, waktu_tunda, status_tunda)
             VALUES (?, ?, ?, ?, ?, NOW(), 'Held')"
        );
        $stmt->execute([
            $kodeTunda,
            $idKasir,
            $label,
            json_encode($items, JSON_UNESCAPED_UNICODE),
            (float) $calc['total_harga'],
        ]);

        AuditLogger::log('transaksi_tertunda', 'HOLD_CART', $kodeTunda, null, ['label' => $label, 'subtotal' => $calc['total_harga']]);

        $this->jsonResponse([
            'success'    => true,
            'kode_tunda' => $kodeTunda,
            'message'    => 'keranjang berhasil ditunda sementara',
        ]);
    }

    // panggil kembali (resume) keranjang belanja yang ditunda ke terminal kasir
    public function resumeCart(int $idTunda): void
    {
        AuthFilter::enforce(['kasir', 'admin'], true);
        $db   = Database::connectPdo();
        $stmt = $db->prepare("SELECT * FROM transaksi_tertunda WHERE id_tunda = ? AND status_tunda = 'Held' LIMIT 1");
        $stmt->execute([$idTunda]);
        $held = $stmt->fetch();

        if (!$held) {
            $this->jsonResponse(['error' => 'data transaksi tertunda tidak ditemukan'], 404);
        }

        $upd = $db->prepare("UPDATE transaksi_tertunda SET status_tunda = 'Resumed' WHERE id_tunda = ?");
        $upd->execute([$idTunda]);

        AuditLogger::log('transaksi_tertunda', 'RESUME_CART', $held['kode_tunda']);

        $this->jsonResponse([
            'success' => true,
            'items'   => json_decode($held['payload_keranjang'], true) ?: [],
            'label'   => $held['label_pelanggan'],
        ]);
    }

    // batalkan keranjang yang ditunda
    public function discardHeldCart(int $idTunda): void
    {
        AuthFilter::enforce(['kasir', 'admin'], true);
        $db  = Database::connectPdo();
        $upd = $db->prepare("UPDATE transaksi_tertunda SET status_tunda = 'Discarded' WHERE id_tunda = ?");
        $upd->execute([$idTunda]);
        $this->jsonResponse(['success' => true, 'message' => 'keranjang tunda dihapus']);
    }

    // periksa status shift kasir yang sedang aktif
    public function activeShift(): void
    {
        $user       = AuthFilter::enforce(['kasir', 'admin'], true);
        $idKasir    = $user['role'] === 'kasir' ? $user['id'] : 1;
        $shiftModel = new ShiftRekonModel();
        $this->jsonResponse(['shift' => $shiftModel->getActiveShift($idKasir)]);
    }

    // buka sesi shift baru dengan pencatatan modal kas awal laci
    public function openShift(): void
    {
        $user      = AuthFilter::enforce(['kasir', 'admin'], true);
        $input     = $this->getJsonInput();
        $modalAwal = (float) ($input['starting_cash'] ?? 500000);
        $shiftName = (string) ($input['shift'] ?? ($user['shift'] ?? 'Morning'));
        $idKasir   = $user['role'] === 'kasir' ? $user['id'] : 1;

        $shiftModel = new ShiftRekonModel();
        $existing   = $shiftModel->getActiveShift($idKasir);
        if ($existing) {
            $this->jsonResponse(['error' => 'masih ada shift aktif yang belum ditutup'], 400);
        }

        $db   = Database::connectPdo();
        $stmt = $db->prepare(
            "INSERT INTO rekonsiliasi_shift (id_kasir, shift, waktu_buka, modal_awal, penjualan_tunai_sistem, penjualan_non_tunai_sistem, status)
             VALUES (?, ?, NOW(), ?, 0, 0, 'Open')"
        );
        $stmt->execute([$idKasir, $shiftName, $modalAwal]);

        AuditLogger::log('rekonsiliasi_shift', 'OPEN_SHIFT', (string) $db->lastInsertId(), null, [
            'shift'      => $shiftName,
            'modal_awal' => $modalAwal,
        ]);

        $this->jsonResponse([
            'success' => true,
            'shift'   => $shiftModel->getActiveShift($idKasir),
            'message' => 'shift kasir berhasil dibuka',
        ]);
    }

    // tutup sesi shift kasir dan hitung selisih antara kas fisik dan catatan sistem
    public function closeShift(): void
    {
        $user     = AuthFilter::enforce(['kasir', 'admin'], true);
        $input    = $this->getJsonInput();
        $idRekon  = (int) ($input['id_rekon'] ?? 0);
        $kasFisik = (float) ($input['physical_cash'] ?? 0);
        $catatan  = trim((string) ($input['notes'] ?? ''));

        $db   = Database::connectPdo();
        $stmt = $db->prepare("SELECT * FROM rekonsiliasi_shift WHERE id_rekon = ? AND status = 'Open' LIMIT 1");
        $stmt->execute([$idRekon]);
        $rekon = $stmt->fetch();

        if (!$rekon) {
            $this->jsonResponse(['error' => 'data shift aktif tidak ditemukan'], 404);
        }

        $kasSistemSeharusnya = (float) $rekon['modal_awal'] + (float) $rekon['penjualan_tunai_sistem'];
        $selisihKas          = round($kasFisik - $kasSistemSeharusnya, 2);

        $upd = $db->prepare(
            "UPDATE rekonsiliasi_shift
             SET waktu_tutup = NOW(), kas_fisik_akhir = ?, selisih_kas = ?, catatan = ?, status = 'Closed'
             WHERE id_rekon = ?"
        );
        $upd->execute([$kasFisik, $selisihKas, $catatan, $idRekon]);

        AuditLogger::log('rekonsiliasi_shift', 'CLOSE_SHIFT', (string) $idRekon, [
            'kas_sistem' => $kasSistemSeharusnya,
        ], [
            'kas_fisik'   => $kasFisik,
            'selisih_kas' => $selisihKas,
        ]);

        $this->jsonResponse([
            'success'        => true,
            'expected_cash'  => $kasSistemSeharusnya,
            'physical_cash'  => $kasFisik,
            'discrepancy'    => $selisihKas,
            'message'        => 'rekonsiliasi shift berhasil disimpan',
        ]);
    }
}
