<?php

use Illuminate\Support\Facades\DB;

/**
 * Katalog awal parameter uji.
 * - Kelompok & kode = header kolom Buku Sampel (10. BUKU SAMPEL OKTOBER.xlsx).
 * - Nama parameter, persyaratan mutu, metode acuan, keterangan = teks persis dari LHP asli instansi
 *   (folder LHP 2026: MANDIRI, SURVEILEN, PENGUJIAN EKSTERNAL, MONITORING, TEST RESULT, LHU CESIUM, OFFICIAL CONTROL).
 * - Yang tidak ada contohnya di LHP asli dibiarkan kosong (diisi operator/Superadmin), tidak dikarang.
 * - Teks di antara _garis bawah_ dicetak miring; "\n" = baris baru di sel LHP; "^" = pangkat (10^5 -> 10⁵).
 * Idempoten: parameter yang sudah ada tidak ditimpa (bisa sudah diedit Superadmin).
 */
return function (): void {
    $lab = fn (string $n) => DB::table('labs')->where('nama', $n)->value('id');
    $bio = ['Biologi', 'Biology'];
    $kim = ['Kimia', 'Chemical'];

    // [lab, kelompok, kode, [bidang, bidang_en], nama, satuan, pilihan[], persyaratan[], metode, keterangan, aktif]
    $data = [
        // ---------------- BAKTERIOLOGI ----------------
        ['Mikrobiologi', 'BAKTERIOLOGI', 'ALT', $bio, 'Angka Lempeng Total (ALT) Aerob', 'kol/g', ['0 kol/g'],
            ['Maks 1,0 x 10^5 kol/g', 'Maks 5,0 x 10^5 kol/g', 'Maks 1,0 x 10^4 kol/g', '0 kol/g'], 'SNI 2332.3-2015', null, true],
        ['Mikrobiologi', 'BAKTERIOLOGI', 'E', $bio, '_Escherichia coli_', 'APM/g', ['Negatif/25 g', 'Positif/25 g', '< 3,0 APM/g'],
            ['Negatif/25 g', '< 3,0 APM/g'], 'ISO 16649-3-2015', null, true],
        ['Mikrobiologi', 'BAKTERIOLOGI', 'S', $bio, '_Salmonella_ spp.', null, ['Negatif/25 g', 'Positif/25 g'],
            ['Negatif/25 g'], 'SNI ISO 6579-1:2017', null, true],
        ['Mikrobiologi', 'BAKTERIOLOGI', 'VP', $bio, '_Vibrio parahaemolyticus_', 'APM/g', ['< 3,0 APM/g'],
            ['< 3,0 APM/g'], 'SNI 01-2332.5-2006', null, true],
        ['Mikrobiologi', 'BAKTERIOLOGI', 'VC', $bio, '_Vibrio cholerae_', null, ['Negatif/25 g', 'Positif/25 g'],
            ['Negatif/25 g'], 'SNI 01-2332.4-2006', null, true],
        ['Mikrobiologi', 'BAKTERIOLOGI', 'SA', $bio, '_Staphylococcus aureus_', 'kol/g', ['< 3,0 APM/g'],
            ['Maks 1,0 x 10^3 kol/g', 'Maks. 1,0 x 10^2 kol/g', '< 3,0 APM/g'], 'SNI 2332.9-2015', null, true],
        ['Mikrobiologi', 'BAKTERIOLOGI', 'CL', $bio, '_Coliform_', 'APM/g', ['< 3,0 APM/g'],
            ['< 3,0 APM/g'], 'SNI 2332.1-2015', null, true],
        // ---------------- MEMBRAN FILTER (air & es) ----------------
        ['Mikrobiologi', 'MEMBRAN FILTER', 'EC', $bio, '_Escherichia coli_', 'kol/100 ml', ['0 kol/100 ml'],
            ['0 kol/100 ml'], 'ISO 9308-1:2014 / Amd.1:2016(E)', null, true],
        ['Mikrobiologi', 'MEMBRAN FILTER', 'CL', $bio, '_Coliform_', 'kol/100 ml', ['0 kol/100 ml'],
            ['0 kol/100 ml'], 'ISO 9308-1:2014 / Amd.1:2016(E)', null, true],
        ['Mikrobiologi', 'MEMBRAN FILTER', 'ETC', $bio, '_Enterococci_', 'kol/100 ml', ['0 kol/100 ml'],
            ['0 kol/100 ml'], 'SNI ISO 7899-2-2010', null, true],
        // ---------------- KIMIA ----------------
        ['Kimia', 'KIMIA', 'F', $kim, 'Formalin', null, ['Negatif', 'Positif'],
            ['Negatif'], 'IKM/OK/LP/SUB II/6', null, true],
        ['Kimia', 'KIMIA', 'H', $kim, 'Histamin', 'mg/kg', ['ND'],
            ['MRL 100 mg/kg'], 'ISO 19343:2017', "LoD 6,03 mg/kg\nLoQ 19,91 mg/kg", true],
        ['Kimia', 'KIMIA', 'CAP', $kim, 'Chloramphenicol', 'µg/kg', ['ND'],
            ['RPA 0,15 µg/kg'], 'IKM/OK/LP/SUB II/18', 'CCβ 0,075 µg/kg', true],
        ['Kimia', 'KIMIA', 'AMOZ', $kim, 'AMOZ', 'µg/kg', ['ND'],
            ['RPA 0,5 µg/kg'], 'IKM/OK/LP/SUB II/13', 'CCβ 0,25 µg/kg', true],
        ['Kimia', 'KIMIA', 'AOZ', $kim, 'AOZ', 'µg/kg', ['ND'],
            ['RPA 0,5 µg/kg'], 'IKM/OK/LP/SUB II/12', 'CCβ 0,25 µg/kg', true],
        // Belum ada contoh LHP/LHPS: persyaratan & metode dilengkapi Superadmin di Katalog parameter.
        ['Kimia', 'KIMIA', 'AHD', $kim, 'AHD', 'µg/kg', ['ND'], [], null, null, true],
        ['Kimia', 'KIMIA', 'SEM', $kim, 'SEM', 'µg/kg', ['ND'], [], null, null, true],
        ['Kimia', 'KIMIA', 'PB', $kim, 'Timbal (Pb)', 'mg/kg', ['ND'],
            ['< 0,3 mg/kg'], "SNI 2354-5:2011\n(Konfirmasi 2021)", "LoD 0,01 mg/kg\nLoQ 0,02 mg/kg", true],
        ['Kimia', 'KIMIA', 'CD', $kim, 'Cadmium (Cd)', 'mg/kg', ['ND'],
            ['< 0,05 mg/kg'], "SNI 2354-5:2011\n(Konfirmasi 2021)", "LoD 0,005 mg/kg\nLoQ 0,006 mg/kg", true],
        ['Kimia', 'KIMIA', 'HG', $kim, 'Merkuri (Hg)', 'mg/kg', ['ND'],
            ['< 0,5 mg/kg', 'Maks. 0,50 mg/kg'], 'SNI 2354.6:2016', "LoD 0,03 mg/kg\nLoQ 0,05 mg/kg", true],
        // Kolom J & Biokimia Bakteri ada di Buku Sampel tetapi belum ada contoh LHP-nya: nonaktif sampai dilengkapi Superadmin.
        ['Kimia', 'KIMIA', 'J', $kim, 'J', null, [], [], null, null, false],
        ['Kimia', 'KIMIA', 'BB', $kim, 'Biokimia Bakteri', null, [], [], null, null, false],
        // ---------------- ORGANOLEPTIK ----------------
        // Sub-item sensory test (Keadaan Beku, Thawing, Keadaan Segar, Produk Kering, Produk Kaleng) dipasang
        // oleh migrasi 2026_11_18_000001_organoleptik_sub_item, sama seperti LHPS Organoleptik.
        ['Organoleptik', 'O', 'O', ['Organoleptik', 'Organoleptic'], 'Sensory test', null, ['7,0', '8,0', '9,0'],
            ['Minimal 7,0'], 'SNI 2346:2015', null, true],
        // ---------------- RADIONUKLIDA ----------------
        ['Radionuklida', 'RADIONUKLIDA', 'CS', ['Radionuklida', 'Radionuclide'], 'Cesium (Cs-137)', 'Bq/kg', ['Not Detected'],
            ['< 8 Bq/kg (LoQ USFDA)'], "IKM/OK/LP/SUB II/1\n(Spektro Gamma HPGe)*", null, true],
    ];

    $adaSubItem = \Illuminate\Support\Facades\Schema::hasColumn('parameter_uji', 'sub_item');
    foreach ($data as $i => [$labNama, $kel, $kode, [$bidang, $bidangEn], $nama, $satuan, $pilihan, $syarat, $metode, $ket, $aktif]) {
        $labId = $lab($labNama);
        if (! $labId) {
            continue;
        }
        $ada = DB::table('parameter_uji')->where(['lab_id' => $labId, 'kelompok' => $kel, 'kode' => $kode]);
        if ($ada->exists()) {
            // Sensory test lama tanpa sub-item: lengkapi sub-item LHPS.
            if ($adaSubItem && $kode === 'O') {
                (clone $ada)->whereNull('sub_item')->update(['sub_item' => json_encode(\App\Models\ParameterUji::SUB_ITEM_ORGANOLEPTIK, JSON_UNESCAPED_UNICODE)]);
            }
            continue;
        }
        DB::table('parameter_uji')->insert([
            'lab_id' => $labId, 'kelompok' => $kel, 'kode' => $kode, 'bidang' => $bidang, 'bidang_en' => $bidangEn,
            'nama' => $nama, 'satuan' => $satuan, 'pilihan' => json_encode($pilihan, JSON_UNESCAPED_UNICODE),
            'ulangan' => 1, 'persyaratan' => json_encode($syarat, JSON_UNESCAPED_UNICODE), 'metode' => $metode,
            'keterangan' => $ket, 'urutan' => $i + 1, 'aktif' => $aktif, 'created_at' => now(), 'updated_at' => now(),
        ] + ($adaSubItem && $kode === 'O' ? ['sub_item' => json_encode(\App\Models\ParameterUji::SUB_ITEM_ORGANOLEPTIK, JSON_UNESCAPED_UNICODE)] : []));
    }
};
