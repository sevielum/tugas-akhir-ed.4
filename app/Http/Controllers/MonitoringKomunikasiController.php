<?php

namespace App\Http\Controllers;

use App\Models\SlavePacketLoss;
use App\Models\SlaveDelay;
use App\Models\Slave1;
use App\Models\Slave2;
use Illuminate\Http\Request;
use Carbon\Carbon;
use DB;

class MonitoringKomunikasiController extends Controller
{
    public function index(Request $request)
    {
        $title = 'Monitoring Komunikasi';

        // Ambil tanggal dari input atau default ke hari ini
        $tanggal = $request->input('tanggal', Carbon::now()->toDateString());

        // Mengambil data dari tabel slave_packet_losses dan slave_delays berdasarkan tanggal
        $dataPacketLoss = SlavePacketLoss::whereDate('sent_at', $tanggal)->orderBy('sent_at','DESC')->whereNotNull('lost_packets')->get(); // Data Packet Loss
        $dataDelay = SlaveDelay::whereDate('sent_at', $tanggal)->orderBy('sent_at','DESC')->whereNotNull('delay')->get(); // Data Delay

        // Memetakan relasi ke Slave1 atau Slave2 secara eksplisit
        $dataPacketLoss->map(function ($item) {
            if ($item->slave_type === 'slave1') {
                $item->slave = Slave1::find($item->slave_id);  // Menghubungkan ke Slave1
            } elseif ($item->slave_type === 'slave2') {
                $item->slave = Slave2::find($item->slave_id);  // Menghubungkan ke Slave2
            }
        });

        $dataDelay->map(function ($item) {
            if ($item->slave_type === 'slave1') {
                $item->slave = Slave1::find($item->slave_id);  // Menghubungkan ke Slave1
            } elseif ($item->slave_type === 'slave2') {
                $item->slave = Slave2::find($item->slave_id);  // Menghubungkan ke Slave2
            }
        });

        // Menggabungkan kedua data menjadi satu koleksi
        $data = $dataPacketLoss->merge($dataDelay);

        // Persiapkan data untuk grafik
        $delayData = $data->take(100)->pluck('delay'); // Mengambil data delay
        $packetLossData = $data->take(100)->pluck('lost_packets')->map(function ($value) {
            // Mengganti null dengan 0 dan memastikan hanya ada 0 atau 1
            return $value == null ? 0 : $value;
        });        // Mengambil data packet loss percentage
        $labels = $data->take(100)->pluck('sent_at')->map(function ($date) {
            return Carbon::parse($date)->format('H:i'); // Format jam dan menit
        });

        // Menghitung rata-rata delay per tanggal
        $averageDelay = $data->avg('delay');

        // Menghitung persentase packet loss per tanggal
        $totalLostPackets = $data->sum('lost_packets');
        $totalPackets = $data->sum('total_packets');
        $packetLossPercentage = $totalPackets > 0 ? ($totalLostPackets / $totalPackets) * 100 : 0;

        // Kirim data ke view
        return view('monitoring-komunikasi', compact('title', 'data', 'tanggal', 'delayData', 'packetLossData', 'labels', 'averageDelay', 'packetLossPercentage'));
    }
}
