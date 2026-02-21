<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Models\ArSys\Research;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/debug-supervise', function () {
    // --- SIMULASI USER LOGIN ---
    $user = User::find(1);
    if (!$user || !$user->staff) {
        dd('DEBUG: User atau relasi Staff tidak ditemukan.');
    }

    // Ambil satu record supervisor dari riset yang aktif
    $firstActiveSupervisorRecord = $user->staff->firstSPVActive()->first();
    if (!$firstActiveSupervisorRecord) {
        dd('DEBUG: Staff ini tidak memiliki riset AKTIF sebagai pembimbing utama.');
    }

    // Ambil objek risetnya
    $research = $firstActiveSupervisorRecord->research;
    if (!$research) {
        dd('DEBUG: Gagal mengakses relasi "research" dari supervisor record.');
    }

    // --- INI ADALAH TES UTAMA ---
    $milestoneIdFromDb = $research->milestone_id;
    $milestoneRelationResult = $research->milestone; // Lazy load the relation

    dd(
        '====== HASIL DEBUG MILESTONE UNTUK RISET AKTIF ======',
        'Research ID: ' . $research->id,
        'Research Title: ' . $research->title,
        '---',
        'Nilai kolom "milestone_id" di database untuk riset ini adalah:',
        $milestoneIdFromDb,
        '---',
        'Hasil dari relasi $research->milestone:',
        $milestoneRelationResult,
        '---',
        'KESIMPULAN: Jika nilai "milestone_id" di atas adalah NULL, maka relasi akan selalu mengembalikan NULL. Ini adalah masalah pada data di tabel `arsys_research`, bukan pada kode.'
    );
});
