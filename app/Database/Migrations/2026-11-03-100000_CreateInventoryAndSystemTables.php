<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// buat tabel inventaris lanjutan, batch fefo, po pemasok, opname, promosi, audit, dan backup
class CreateInventoryAndSystemTables extends Migration
{
    // jalankan pembuatan tabel inventaris dan sistem
    public function up(): void
    {
        $this->forge->addField([
            'id_batch'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'id_barang'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'nomor_batch'        => ['type' => 'VARCHAR', 'constraint' => 80],
            'tanggal_masuk'      => ['type' => 'DATE'],
            'tanggal_kadaluarsa' => ['type' => 'DATE'],
            'stok_awal'          => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'stok_tersedia'      => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'harga_beli_batch'   => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'status_batch'       => ['type' => 'ENUM', 'constraint' => ['Active', 'Near_Expiry', 'Expired', 'Depleted'], 'default' => 'Active'],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id_batch', true);
        $this->forge->createTable('batch_barang', true);

        $this->forge->addField([
            'id_pemasok'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'kode_pemasok'  => ['type' => 'VARCHAR', 'constraint' => 40, 'unique' => true],
            'nama_pemasok'  => ['type' => 'VARCHAR', 'constraint' => 150],
            'kontak_person' => ['type' => 'VARCHAR', 'constraint' => 100],
            'no_telepon'    => ['type' => 'VARCHAR', 'constraint' => 30],
            'alamat'        => ['type' => 'TEXT', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id_pemasok', true);
        $this->forge->createTable('pemasok', true);

        $this->forge->addField([
            'id_pembelian'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'no_faktur_masuk'   => ['type' => 'VARCHAR', 'constraint' => 80, 'unique' => true],
            'id_pemasok'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'id_admin'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'tanggal_pembelian' => ['type' => 'DATETIME'],
            'total_unit_masuk'  => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'total_biaya'       => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'catatan'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
        $this->forge->addKey('id_pembelian', true);
        $this->forge->createTable('pembelian_stok', true);

        $this->forge->addField([
            'id_detail_beli'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'id_pembelian'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'id_barang'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'jumlah_kemasan'     => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'unit_per_kemasan'   => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'total_unit'         => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'harga_beli_satuan'  => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'subtotal_biaya'     => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'nomor_batch'        => ['type' => 'VARCHAR', 'constraint' => 80],
            'tanggal_kadaluarsa' => ['type' => 'DATE'],
        ]);
        $this->forge->addKey('id_detail_beli', true);
        $this->forge->createTable('detail_pembelian_stok', true);

        $this->forge->addField([
            'id_opname'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'kode_opname'         => ['type' => 'VARCHAR', 'constraint' => 60, 'unique' => true],
            'id_admin'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'id_barang'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'stok_sistem'         => ['type' => 'INT', 'constraint' => 11],
            'stok_fisik'          => ['type' => 'INT', 'constraint' => 11],
            'selisih_unit'        => ['type' => 'INT', 'constraint' => 11],
            'nominal_selisih'     => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'alasan_selisih'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'disesuaikan_ke_stok' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'tanggal_opname'      => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id_opname', true);
        $this->forge->createTable('stok_opname', true);

        $this->forge->addField([
            'id_promo'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'kode_promo'      => ['type' => 'VARCHAR', 'constraint' => 50, 'unique' => true],
            'nama_promo'      => ['type' => 'VARCHAR', 'constraint' => 140],
            'id_barang'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'tipe_promo'      => ['type' => 'ENUM', 'constraint' => ['Bundle_Pair', 'Min_Qty_Cut', 'Percent_Discount'], 'default' => 'Bundle_Pair'],
            'min_kuantitas'   => ['type' => 'INT', 'constraint' => 11, 'default' => 2],
            'harga_paket'     => ['type' => 'DECIMAL', 'constraint' => '14,2', 'null' => true],
            'potongan_rupiah' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'persen_diskon'   => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0.00],
            'tanggal_mulai'   => ['type' => 'DATE'],
            'tanggal_akhir'   => ['type' => 'DATE'],
            'status_aktif'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ]);
        $this->forge->addKey('id_promo', true);
        $this->forge->createTable('promosi_bundling', true);

        $this->forge->addField([
            'id_log'          => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'peran_pelaku'    => ['type' => 'ENUM', 'constraint' => ['admin', 'kasir', 'system']],
            'id_pelaku'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'username_pelaku' => ['type' => 'VARCHAR', 'constraint' => 60],
            'aksi'            => ['type' => 'VARCHAR', 'constraint' => 80],
            'modul'           => ['type' => 'VARCHAR', 'constraint' => 80],
            'id_entitas'      => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'data_lama'       => ['type' => 'TEXT', 'null' => true],
            'data_baru'       => ['type' => 'TEXT', 'null' => true],
            'ip_address'      => ['type' => 'VARCHAR', 'constraint' => 45],
            'user_agent'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'waktu_kejadian'  => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id_log', true);
        $this->forge->createTable('log_audit', true);

        $this->forge->addField([
            'id_backup'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nama_file'     => ['type' => 'VARCHAR', 'constraint' => 180],
            'ukuran_bytes'  => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 0],
            'lokasi_simpan' => ['type' => 'VARCHAR', 'constraint' => 255],
            'tipe_pemicu'   => ['type' => 'ENUM', 'constraint' => ['Scheduled_Cron', 'Manual_Admin'], 'default' => 'Scheduled_Cron'],
            'id_admin'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'status_backup' => ['type' => 'ENUM', 'constraint' => ['Success', 'Failed'], 'default' => 'Success'],
            'waktu_backup'  => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id_backup', true);
        $this->forge->createTable('riwayat_backup', true);
    }

    // hapus tabel inventaris dan sistem
    public function down(): void
    {
        $this->forge->dropTable('riwayat_backup', true);
        $this->forge->dropTable('log_audit', true);
        $this->forge->dropTable('promosi_bundling', true);
        $this->forge->dropTable('stok_opname', true);
        $this->forge->dropTable('detail_pembelian_stok', true);
        $this->forge->dropTable('pembelian_stok', true);
        $this->forge->dropTable('pemasok', true);
        $this->forge->dropTable('batch_barang', true);
    }
}
