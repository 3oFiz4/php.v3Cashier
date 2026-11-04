<?php

namespace App\Libraries;

use Config\PosConfig;

// bangun string payload qris dinamis standar emvco beserta checksum crc16
class QrisGenerator
{
    // hasilkan payload qris dinamis berdasarkan nominal tagihan transaksi
    public static function generatePayload(float $amount, string $referenceCode): array
    {
        $config       = new PosConfig();
        $merchantName = substr(strtoupper($config->qrisMerchantName), 0, 25);
        $merchantCity = substr(strtoupper($config->qrisMerchantCity), 0, 15);
        $nmid         = $config->qrisMerchantNmid;
        $amountStr    = (string) round($amount);

        $payload = '000201'
            . '010212'
            . self::formatTlv('26', self::formatTlv('00', 'ID.CO.QRIS.WWW') . self::formatTlv('01', $nmid))
            . '52045411'
            . '5303360'
            . self::formatTlv('54', $amountStr)
            . '5802ID'
            . self::formatTlv('59', $merchantName)
            . self::formatTlv('60', $merchantCity)
            . self::formatTlv('62', self::formatTlv('01', $referenceCode))
            . '6304';

        $crc = self::crc16Ccitt($payload);
        $fullQris = $payload . $crc;

        return [
            'qris_string'    => $fullQris,
            'reference_code' => $referenceCode,
            'merchant_name'  => $merchantName,
            'nmid'           => $nmid,
            'amount'         => $amount,
            'expires_at'     => date('Y-m-d H:i:s', time() + 300),
        ];
    }

    // format pasangan tag-length-value emvco
    private static function formatTlv(string $tag, string $value): string
    {
        $length = str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT);
        return $tag . $length . $value;
    }

    // hitung nilai checksum crc16-ccitt heksadesimal 4 karakter
    private static function crc16Ccitt(string $data): string
    {
        $crc = 0xFFFF;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                if (($crc & 0x8000) !== 0) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
