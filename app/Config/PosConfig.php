<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

// simpan parameter operasional minimarket ramstore
class PosConfig extends BaseConfig
{
    public string $storeName = 'Ramstore Minimarket';
    public string $storeTagline = 'Belanja Hemat & Cepat Setiap Hari';
    public string $storeAddress = 'Jl. Raya Denpasar No. 88, Bali';
    public string $storePhone = '0361-889900';
    public string $receiptPrefix = 'RMS';
    public int $lowStockDefault = 15;
    public int $expiryWarningDays = 3;
    public string $qrisMerchantName = 'RAMSTORE MINIMARKET BALI';
    public string $qrisMerchantCity = 'DENPASAR';
    public string $qrisMerchantNmid = 'ID1026008899001';
    public int $backupRetentionDays = 30;
}
