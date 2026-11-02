<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// buat tabel master admin, kasir, jenis_barang, dan barang
class CreateMasterTables extends Migration
{
    // jalankan pembuatan skema tabel utama
    public function up(): void
    {
        $this->forge->addField([
            'id_admin'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nama_lengkap'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'username'           => ['type' => 'VARCHAR', 'constraint' => 60, 'unique' => true],
            'password'           => ['type' => 'VARCHAR', 'constraint' => 255],
            'pin_supervisor'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'tanggal_registrasi' => ['type' => 'DATETIME', 'null' => false],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id_admin', true);
        $this->forge->createTable('admin', true);

        $this->forge->addField([
            'id_kasir'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nama_lengkap' => ['type' => 'VARCHAR', 'constraint' => 120],
            'username'     => ['type' => 'VARCHAR', 'constraint' => 60, 'unique' => true],
            'password'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'no_telepon'   => ['type' => 'VARCHAR', 'constraint' => 25],
            'shift'        => ['type' => 'ENUM', 'constraint' => ['Morning', 'Afternoon', 'Night'], 'default' => 'Morning'],
            'status_aktif' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id_kasir', true);
        $this->forge->createTable('kasir', true);

        $this->forge->addField([
            'id_jenis'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nama_jenis' => ['type' => 'VARCHAR', 'constraint' => 100, 'unique' => true],
            'deskripsi'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id_jenis', true);
        $this->forge->createTable('jenis_barang', true);

        $this->forge->addField([
            'id_barang'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'id_jenis'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'barcode'      => ['type' => 'VARCHAR', 'constraint' => 64, 'unique' => true],
            'nama_barang'  => ['type' => 'VARCHAR', 'constraint' => 160],
            'harga_beli'   => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'harga_jual'   => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'stok'         => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'stok_minimum' => ['type' => 'INT', 'constraint' => 11, 'default' => 15],
            'gambar'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id_barang', true);
        $this->forge->addForeignKey('id_jenis', 'jenis_barang', 'id_jenis', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('barang', true);
    }

    // batalkan pembuatan tabel utama secara berurutan
    public function down(): void
    {
        $this->forge->dropTable('barang', true);
        $this->forge->dropTable('jenis_barang', true);
        $this->forge->dropTable('kasir', true);
        $this->forge->dropTable('admin', true);
    }
}
