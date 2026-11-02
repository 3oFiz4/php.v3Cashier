<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// buat tabel transaksi, detail_transaksi, rekonsiliasi_shift, transaksi_tertunda, dan otorisasi_void
class CreateTransactionTables extends Migration
{
    // jalankan migrasi tabel transaksi kasir
    public function up(): void
    {
        $this->forge->addField([
            'id_rekon'                   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'id_kasir'                   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'shift'                      => ['type' => 'ENUM', 'constraint' => ['Morning', 'Afternoon', 'Night']],
            'waktu_buka'                 => ['type' => 'DATETIME'],
            'waktu_tutup'                => ['type' => 'DATETIME', 'null' => true],
            'modal_awal'                 => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'penjualan_tunai_sistem'     => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'penjualan_non_tunai_sistem' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'kas_fisik_akhir'            => ['type' => 'DECIMAL', 'constraint' => '14,2', 'null' => true],
            'selisih_kas'                => ['type' => 'DECIMAL', 'constraint' => '14,2', 'null' => true],
            'catatan'                    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'status'                     => ['type' => 'ENUM', 'constraint' => ['Open', 'Closed'], 'default' => 'Open'],
        ]);
        $this->forge->addKey('id_rekon', true);
        $this->forge->addForeignKey('id_kasir', 'kasir', 'id_kasir', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('rekonsiliasi_shift', true);

        $this->forge->addField([
            'id_transaksi'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'no_struk'             => ['type' => 'VARCHAR', 'constraint' => 60, 'unique' => true],
            'tanggal_transaksi'    => ['type' => 'DATETIME'],
            'id_kasir'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'id_rekon'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'total_item'           => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'subtotal_awal'        => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'total_diskon'         => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'total_harga'          => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'metode_pembayaran'    => ['type' => 'ENUM', 'constraint' => ['Cash', 'Debit', 'E-Wallet', 'QRIS', 'Split'], 'default' => 'Cash'],
            'bayar_tunai'          => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'bayar_non_tunai'      => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'referensi_pembayaran' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'jumlah_bayar'         => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'kembalian'            => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'status_transaksi'     => ['type' => 'ENUM', 'constraint' => ['Completed', 'Voided', 'Refunded'], 'default' => 'Completed'],
        ]);
        $this->forge->addKey('id_transaksi', true);
        $this->forge->addForeignKey('id_kasir', 'kasir', 'id_kasir', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('transaksi', true);

        $this->forge->addField([
            'id_detail'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'id_transaksi'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'id_barang'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'id_batch'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'nama_barang_snapshot' => ['type' => 'VARCHAR', 'constraint' => 160],
            'harga_beli_satuan'    => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'harga_satuan'         => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'jumlah'               => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'diskon_item'          => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'subtotal'             => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
        ]);
        $this->forge->addKey('id_detail', true);
        $this->forge->addForeignKey('id_transaksi', 'transaksi', 'id_transaksi', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('id_barang', 'barang', 'id_barang', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('detail_transaksi', true);

        $this->forge->addField([
            'id_tunda'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'kode_tunda'         => ['type' => 'VARCHAR', 'constraint' => 50, 'unique' => true],
            'id_kasir'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'label_pelanggan'    => ['type' => 'VARCHAR', 'constraint' => 120],
            'payload_keranjang'  => ['type' => 'TEXT'],
            'subtotal_sementara' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'waktu_tunda'        => ['type' => 'DATETIME'],
            'status_tunda'       => ['type' => 'ENUM', 'constraint' => ['Held', 'Resumed', 'Discarded'], 'default' => 'Held'],
        ]);
        $this->forge->addKey('id_tunda', true);
        $this->forge->createTable('transaksi_tertunda', true);

        $this->forge->addField([
            'id_void'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'id_kasir'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'id_admin_supervisor' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'id_transaksi'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'id_barang'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'jenis_void'          => ['type' => 'ENUM', 'constraint' => ['Void_Cart_Item', 'Clear_Active_Cart', 'Refund_Receipt']],
            'nominal_terdampak'   => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'alasan_void'         => ['type' => 'VARCHAR', 'constraint' => 255],
            'waktu_otorisasi'     => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id_void', true);
        $this->forge->createTable('otorisasi_void', true);
    }

    // batalkan migrasi tabel transaksi
    public function down(): void
    {
        $this->forge->dropTable('otorisasi_void', true);
        $this->forge->dropTable('transaksi_tertunda', true);
        $this->forge->dropTable('detail_transaksi', true);
        $this->forge->dropTable('transaksi', true);
        $this->forge->dropTable('rekonsiliasi_shift', true);
    }
}
