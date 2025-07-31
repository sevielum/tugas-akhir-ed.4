@extends('layouts.main')

@section('panel')
    <div class="pagetitle">
        <h1 style="margin-bottom: 30px;">{{ $title }}</h1>
        <form method="GET" action="{{ route('monitoring-komunikasi') }}" class="mb-4">
            <label for="tanggal">Filter Tanggal:</label>
            <input type="date" name="tanggal" id="tanggal" value="{{ request('tanggal', $tanggal) }}">
            <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>
        </form>

        <div class="alert alert-info" role="alert">
            Menampilkan data untuk tanggal: <strong>{{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}</strong>
        </div>
    </div>
@endsection

@section('content')
<div class="alert alert-success" role="alert">
        <strong>Delay Rata-rata per Hari:</strong> {{ $averageDelay }} ms
    </div>

    <div class="alert alert-success" role="alert">
        <strong>Packet Loss per Hari:</strong> {{ $packetLossPercentage }} %
    </div>

    <!-- Grafik Delay -->
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-body">
                    <h5 class="card-title">Grafik Delay</h5>
                    <div id="delayChart" style="height: 400px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Grafik Packet Loss -->
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-body">
                    <h5 class="card-title">Grafik Packet Loss</h5>
                    <div id="packetLossChart" style="height: 400px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Gabungan Delay dan Packet Loss -->
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-body">
                    <h5 class="card-title">Tabel Delay dan Packet Loss</h5>
                    <table class="table table-bordered" id="datatable1">
                        <thead>
                            <tr>
                                <th class="text-center">No.</th>
                                <th class="text-center">Waktu Kirim</th>
                                <th class="text-center">Waktu Diterima</th>
                                <th class="text-center">Delay (ms)</th>
                                <th class="text-center">Packet Loss</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data as $row)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $row->sent_at ?? 'N/A' }}</td>
                                    <td>{{ $row->received_at ?? 'N/A' }}</td>
                                    <td>{{ $row->delay ?? 'N/A' }}</td>
                                    <td>{{ $row->lost_packets == 0 ? 'Tidak Ada' : 'Ada' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
    $(document).ready(function () {
        $('#datatable1').DataTable({
            "searching": false // Menonaktifkan pencarian
        });

        // Grafik Delay
        var optionsDelay = {
            chart: {
                type: 'area',
                height: 350
            },
            series: [{
                name: 'Delay (ms)',
                data: @json($delayData)
            }],
            xaxis: {
                categories: @json($labels),
                title: {
                    text: 'Waktu'
                }
            },
            yaxis: {
                title: {
                    text: 'Delay (ms)'
                },
                min: 0
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.3,
                    opacityTo: 0.1,
                    stops: [0, 100]
                }
            },
            colors: ['#4bc0c0'],
            dataLabels: {
                enabled: true, // Menyalakan data labels jika ingin, tetapi akan dihilangkan menggunakan formatter
                formatter: function() {
            return ''; // Menghilangkan angka di atas titik data
                }
            },
            tooltip: {
                enabled: false // Menonaktifkan tooltips yang muncul saat hover
            }
        };

        var delayChart = new ApexCharts(document.querySelector("#delayChart"), optionsDelay);
        delayChart.render();

        // Grafik Packet Loss
        var optionsPacketLoss = {
            chart: {
                type: 'area',
                height: 350
            },
            series: [{
                name: 'Packet Loss ()',
                data: @json($packetLossData)
            }],
            xaxis: {
                categories: @json($labels),
                title: {
                    text: 'Waktu'
                }
            },
            yaxis: {
                title: {
                    text: 'Packet Loss'
                },
                min: 0,
                max: 1,  // Batas maksimum untuk 100% (packet loss)
                labels: {
                    formatter: function(value) {
                        return (value * 100).toFixed(0);
                    }
                }
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.3,
                    opacityTo: 0.1,
                    stops: [0, 100]
                }
            },
            colors: ['#ff6384'],
            dataLabels: {
                enabled: true, // Menyalakan data labels jika ingin, tetapi akan dihilangkan menggunakan formatter
                formatter: function() {
            return ''; // Menghilangkan angka di atas titik data
                }
            },
            tooltip: {
                enabled: false // Menonaktifkan tooltips yang muncul saat hover
            }
        };

        var packetLossChart = new ApexCharts(document.querySelector("#packetLossChart"), optionsPacketLoss);
        packetLossChart.render();
    });
</script>

@endpush
