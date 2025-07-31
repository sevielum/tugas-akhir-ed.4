<?php

namespace App\Console\Commands;

use App\Models\Slave1;
use App\Models\Slave2;
use App\Models\SlaveDelay;
use App\Models\SlavePacketLoss;
use App\Services\HiveMQService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Log;

class MqttSubscriber extends Command
{
    protected $signature = 'mqtt:subscribe';
    protected $description = 'Subscribe to multiple MQTT topics and process data';

    protected $hiveMQService;

    // Data storage arrays
    protected $array_lux1 = null;
    protected $array_status1 = null;
    protected $array_lux2 = null;
    protected $array_status2 = null;

    // Topics to subscribe to
    protected $topics = [
        'sensor/slave1/lux',
        'sensor/slave1/status',
        'sensor/slave2/lux',
        'sensor/slave2/status',
    ];

    function __construct(HiveMQService $hiveMQService)
    {
        parent::__construct();
        $this->hiveMQService = $hiveMQService;
    }

    function handle()
    {
        $this->info("Start Subscribed to MQTT topics...");

        try {
            $this->hiveMQService->connect();
            $this->info("Successfully connected to MQTT broker");
        } catch (Exception $e) {
            $this->error("Failed to connect to MQTT broker: " . $e->getMessage());
            return 1;
        }

        foreach ($this->topics as $topic) {
            $this->subscribeTopic($topic);
            $this->info("Subscribed to topic: $topic");
        }

        $this->info("Listening for messages on all topics... Press Ctrl+C to stop.");

        while (true) {
            $this->hiveMQService->loop(1);
        }
    }

    function subscribeTopic($topic)
    {
        $this->hiveMQService->subscribe($topic, function ($topic, $message) {
            $this->processMessage($topic, $message);
        });
    }

    function processMessage($topic, $message)
    {
        try {
            switch ($topic) {
                case 'sensor/slave1/lux':
                    if ($message) {
                        $data = json_decode($message, true);
                        $this->array_lux1 = floatval($data['lux']);
                    } else {
                        $data = $this->tryParseJson($message);
                        $this->array_lux1 = $data;
                    }

                    // Display lux value for slave1
                    $this->info("📥 Slave1 Lux: {$this->array_lux1}");
                    $this->processDelayAndPacketLoss($topic, $data, 1);
                    $this->insertIfReady();
                    break;

                case 'sensor/slave1/status':
                    if ($message) {
                        $this->array_status1 = trim($message);
                    } else {
                        $data = $this->tryParseJson($message);
                        $this->array_status1 = $data;
                    }

                    // Display status for slave1
                    $this->info("📥 Slave1 Status: {$this->array_status1}");
                    $this->insertIfReady();
                    break;

                case 'sensor/slave2/lux':
                    if ($message) {
                        $data = json_decode($message, true);
                        $this->array_lux2 = floatval($data['lux']);
                    } else {
                        $data = $this->tryParseJson($message);
                        $this->array_lux2 = $data;
                    }

                    // Display lux value for slave2
                    $this->info("📥 Slave2 Lux: {$this->array_lux2}");
                    $this->processDelayAndPacketLoss($topic, $data, 2);
                    $this->insertIfReady();
                    break;

                case 'sensor/slave2/status':
                    if ($message) {
                        $this->array_status2 = trim($message);
                    } else {
                        $data = $this->tryParseJson($message);
                        $this->array_status2 = $data;
                    }

                    // Display status for slave2
                    $this->info("📥 Slave2 Status: {$this->array_status2}");
                    $this->insertIfReady();
                    break;

                default:
                    // Warn about unhandled topics
                    $this->warn("Received message on unhandled topic: $topic");
                    break;
            }
        } catch (Exception $e) {
            // Log the error with exception details
            $this->error("Error processing message: " . $e->getMessage());
        }
    }

    function tryParseJson($message)
    {
        $data = json_decode($message, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->warn("Failed to parse JSON: " . json_last_error_msg());
            return false;
        }
        return $data;
    }

    function insertIfReady()
    {
        // Slave1
        if ($this->array_lux1 && $this->array_status1) {
            $this->info("📥 Data untuk Slave1: {$this->array_lux1}, {$this->array_status1}");

            try {
                Slave1::create([
                    'waktu' => Carbon::now('Asia/Jakarta'),
                    'value' => $this->array_lux1,
                    'status' => $this->array_status1,
                ]);
                $this->info("✅ Slave1 Tersimpan: {$this->array_lux1}, {$this->array_status1}");
            } catch (\Exception $e) {
                $this->error("❌ Gagal menyimpan data Slave1: " . $e->getMessage());
            }

            $this->array_lux1 = null;
            $this->array_status1 = null;
        }

        // Slave2
        if ($this->array_lux2 && $this->array_status2) {
            $this->info("📥 Data untuk Slave2: {$this->array_lux2}, {$this->array_status2}");

            try {
                Slave2::create([
                    'waktu' => Carbon::now('Asia/Jakarta'),
                    'value' => $this->array_lux2,
                    'status' => $this->array_status2,
                ]);
                $this->info("✅ Slave2 Tersimpan: {$this->array_lux2}, {$this->array_status2}");
            } catch (\Exception $e) {
                $this->error("❌ Gagal menyimpan data Slave2: " . $e->getMessage());
            }

            $this->array_lux2 = null;
            $this->array_status2 = null;
        }
    }

    function processDelayAndPacketLoss($topic, $data, $slaveId)
    {
        // Mengambil sent_at dari data, jika ada
        $sent_at = isset($data['timestamp']) ? Carbon::createFromTimestamp($data['timestamp']) : null;

        // Mendefinisikan received_at
        $received_at = Carbon::now('Asia/Jakarta'); // Mendapatkan waktu saat data diterima

        // Cek apakah sent_at ada
        if ($sent_at) {
            // Menghitung delay hanya jika sent_at ada
            $delay = $received_at->diffInMilliseconds($sent_at);
            $this->info("📥 Delay untuk Slave{$slaveId}: {$delay} ms");

            // Simpan delay ke database
            SlaveDelay::create([
                'slave_id' => $slaveId,
                'slave_type' => 'slave' . $slaveId,
                'topic' => $topic,
                'delay' => $delay,
                'sent_at' => $sent_at,
                'received_at' => $received_at,
            ]);

            // Menghitung packet loss jika sent_at ada (asumsi tidak ada packet loss jika data diterima dengan sent_at)
            $packetLossPercentage = 0;  // Tidak ada packet loss jika sent_at ada
            $this->info("📥 Packet Loss untuk Slave{$slaveId}: {$packetLossPercentage}%");
        } else {
            // Jika sent_at tidak ada, beri nilai NULL atau 0 untuk delay
            $this->warn("⚠️ Tidak ada informasi waktu pengiriman (sent_at) di data!");

            // Simpan nilai default untuk delay
            SlaveDelay::create([
                'slave_id' => $slaveId,
                'slave_type' => 'slave' . $slaveId,
                'topic' => $topic,
                'delay' => 0,  // Menggunakan nilai default untuk delay
                'sent_at' => null,
                'received_at' => $received_at,
            ]);

            // Jika tidak ada sent_at, anggap ada packet loss
            $packetLossPercentage = 100; // Anggap terjadi packet loss jika sent_at tidak ada
            $this->info("📥 Packet Loss untuk Slave{$slaveId}: {$packetLossPercentage}%");
        }

        // Simpan packet loss ke database
        SlavePacketLoss::create([
            'slave_id' => $slaveId,
            'slave_type' => 'slave' . $slaveId,
            'topic' => $topic,
            'packet_loss_percentage' => $packetLossPercentage,
            'total_packets' => 1,  // Anda dapat mengganti dengan logika yang sesuai untuk total packets
            'lost_packets' => ($packetLossPercentage == 100) ? 1 : 0,
            'sequence_number' => 1,  // Anda dapat mengganti dengan logika yang sesuai untuk sequence number
            'sent_at' => $sent_at,
            'received_at' => $received_at,
        ]);
    }
}
