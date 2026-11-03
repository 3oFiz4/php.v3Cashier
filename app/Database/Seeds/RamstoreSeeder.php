<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

// isi data awal master akun, produk, batch fefo, pemasok, dan promosi
class RamstoreSeeder extends Seeder
{
    // jalankan penyisipan data awal ke tabel database
    public function run(): void
    {
        $hashAdmin = password_hash('admin123', PASSWORD_BCRYPT);
        $hashPin   = password_hash('123456', PASSWORD_BCRYPT);
        $hashKasir = password_hash('kasir123', PASSWORD_BCRYPT);

        $this->db->table('admin')->insertBatch([
            [
                'nama_lengkap'       => 'Rama Wijaya (Super Admin)',
                'username'           => 'admin',
                'password'           => $hashAdmin,
                'pin_supervisor'     => $hashPin,
                'tanggal_registrasi' => date('Y-m-d H:i:s'),
            ],
            [
                'nama_lengkap'       => 'David Pratama (Supervisor)',
                'username'           => 'david',
                'password'           => $hashAdmin,
                'pin_supervisor'     => $hashPin,
                'tanggal_registrasi' => date('Y-m-d H:i:s'),
            ],
        ]);

        $this->db->table('kasir')->insertBatch([
            [
                'nama_lengkap' => 'Merta Sari',
                'username'     => 'kasir_pagi',
                'password'     => $hashKasir,
                'no_telepon'   => '081234567801',
                'shift'        => 'Morning',
                'status_aktif' => 1,
                'created_at'   => date('Y-m-d H:i:s'),
            ],
            [
                'nama_lengkap' => 'Budi Santoso',
                'username'     => 'kasir_siang',
                'password'     => $hashKasir,
                'no_telepon'   => '081234567802',
                'shift'        => 'Afternoon',
                'status_aktif' => 1,
                'created_at'   => date('Y-m-d H:i:s'),
            ],
            [
                'nama_lengkap' => 'Ayu Lestari',
                'username'     => 'kasir_malam',
                'password'     => $hashKasir,
                'no_telepon'   => '081234567803',
                'shift'        => 'Night',
                'status_aktif' => 1,
                'created_at'   => date('Y-m-d H:i:s'),
            ],
        ]);
    }
}
