<?php

namespace App\Controllers;

use App\Filters\AuthFilter;
use App\Models\BarangModel;
use App\Models\BatchBarangModel;
use App\Models\DetailTransaksiModel;
use Config\Database;
use Config\PosConfig;

// kelola ringkasan statistik admin, peringatan stok/kadaluarsa, dan analitik margin
class AdminDashboardController extends BaseController
{
    // tampilkan halaman dashboard manajemen admin
    public function index(): void
    {
        $user = AuthFilter::enforce(['admin'], false);
        $this->renderView('admin/dashboard', ['user' => $user, 'posConfig' => new PosConfig()]);
    }

    // kumpulkan metrik utama pendapatan, stok rendah, kadaluarsa dekat, dan laba kotor
    public function summary(): void
    {
        AuthFilter::enforce(['admin'], true);
        $db    = Database::connectPdo();
        $today = date('Y-m-d');

        $stmtToday = $db->prepare(
            "SELECT COALESCE(SUM(total_harga), 0) AS today_revenue,
                    COUNT(*) AS today_txns
             FROM transaksi
             WHERE DATE(tanggal_transaksi) = ? AND status_transaksi = 'Completed'"
        );
        $stmtToday->execute([$today]);
        $todayData = $stmtToday->fetch();

        $totals = $db->query(
            "SELECT COALESCE(SUM(total_harga), 0) AS total_revenue,
                    COUNT(*) AS total_transactions,
                    COALESCE(SUM(total_item), 0) AS total_items_sold
             FROM transaksi
             WHERE status_transaksi = 'Completed'"
        )->fetch();

        $profitRow = $db->query(
            "SELECT COALESCE(SUM(d.subtotal - (d.harga_beli_satuan * d.jumlah)), 0) AS gross_profit
             FROM detail_transaksi d
             JOIN transaksi t ON d.id_transaksi = t.id_transaksi
             WHERE t.status_transaksi = 'Completed'"
        )->fetch();

        $totalProducts = (int) $db->query("SELECT COUNT(*) FROM barang")->fetchColumn();
        $lowStockCount = (int) $db->query("SELECT COUNT(*) FROM barang WHERE stok > 0 AND stok <= stok_minimum")->fetchColumn();
        $outOfStock    = (int) $db->query("SELECT COUNT(*) FROM barang WHERE stok <= 0")->fetchColumn();

        $batchModel   = new BatchBarangModel();
        $expiryAlerts = $batchModel->getExpiryAlerts(3);

        $barangModel   = new BarangModel();
        $lowStockItems = $barangModel->getLowStockItems();

        $recentTxns = $db->query(
            "SELECT t.*, k.nama_lengkap AS nama_kasir
             FROM transaksi t
             JOIN kasir k ON t.id_kasir = k.id_kasir
             ORDER BY t.tanggal_transaksi DESC LIMIT 10"
        )->fetchAll();

        $bestsellers = $db->query(
            "SELECT d.nama_barang_snapshot AS product_name,
                    SUM(d.jumlah) AS total_sold,
                    SUM(d.subtotal) AS total_revenue
             FROM detail_transaksi d
             JOIN transaksi t ON d.id_transaksi = t.id_transaksi
             WHERE t.status_transaksi = 'Completed'
             GROUP BY d.id_barang, d.nama_barang_snapshot
             ORDER BY total_sold DESC LIMIT 10"
        )->fetchAll();

        $this->jsonResponse([
            'summary' => [
                'today_revenue'      => (float) $todayData['today_revenue'],
                'today_transactions' => (int) $todayData['today_txns'],
                'total_revenue'      => (float) $totals['total_revenue'],
                'total_gross_profit' => (float) $profitRow['gross_profit'],
                'total_transactions' => (int) $totals['total_transactions'],
                'total_items_sold'   => (int) $totals['total_items_sold'],
                'total_products'     => $totalProducts,
                'low_stock'          => $lowStockCount,
                'out_of_stock'       => $outOfStock,
                'near_expiry_count'  => count($expiryAlerts),
            ],
            'low_stock_alerts'    => $lowStockItems,
            'near_expiry_alerts'  => $expiryAlerts,
            'recent_transactions' => $recentTxns,
            'bestsellers'         => $bestsellers,
            'inventory'           => $barangModel->getCatalog(),
        ]);
    }

    // kirim laporan analitik margin laba kotor per kategori dan produk
    public function grossProfitAnalytics(): void
    {
        AuthFilter::enforce(['admin'], true);
        $detailModel = new DetailTransaksiModel();
        $this->jsonResponse($detailModel->getGrossProfitAnalytics());
    }

    // kirim daftar peringatan barang di bawah ambang stok minimum
    public function lowStockAlerts(): void
    {
        AuthFilter::enforce(['admin'], true);
        $barangModel = new BarangModel();
        $this->jsonResponse(['alerts' => $barangModel->getLowStockItems()]);
    }

    // kirim daftar peringatan batch barang mendekati kadaluarsa
    public function expiryAlerts(): void
    {
        AuthFilter::enforce(['admin'], true);
        $batchModel = new BatchBarangModel();
        $this->jsonResponse(['alerts' => $batchModel->getExpiryAlerts(3)]);
    }
}
