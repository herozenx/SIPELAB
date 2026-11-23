<?php

namespace App\Support;

/**
 * Daftar parameter uji bawaan, diambil dari FPPCU 2026 (FL/OK/SUB II/8.5.2.a) dan LHP Balai 2026.
 * Bidang di LHP: Organoleptik, Biologi, Kimia, Radionuklida. akreditasi=false dicetak dengan tanda * di LHP.
 * Tarif belum tercantum di FPPCU, jadi diisi 0: atur di menu "Parameter & tarif".
 */
class ParameterBawaan
{
    /** Lab/bidang pengujian: [nama lab, urutan, norma hari kerja]. */
    public const LAB = [
        ['Organoleptik', 1, 1],
        ['Mikrobiologi', 2, 3],
        ['Kimia', 3, 2],
        ['Radionuklida', 4, 3],
    ];

    /**
     * Nama, metode, persyaratan, dan keterangan (LoD/LoQ, CCβ) disamakan dengan yang tercetak di LHP Balai 2026.
     * Persyaratan bergantung komoditas: nilai di sini hanya bawaan dan bisa diubah per parameter.
     *
     * @return array<int, array{kode:string, nama:string, bidang:string, lab:string, metode:string, persyaratan:?string, satuan:?string, akreditasi:bool, keterangan:?string}>
     */
    public static function daftar(): array
    {
        $p = fn ($kode, $nama, $bidang, $lab, $metode, $syarat = null, $ket = null, $akr = true) => [
            'kode' => $kode, 'nama' => $nama, 'bidang' => $bidang, 'lab' => $lab, 'metode' => $metode,
            'persyaratan' => $syarat, 'satuan' => null, 'akreditasi' => $akr, 'keterangan' => $ket,
        ];

        return [
            $p('O', 'Sensory test', 'Organoleptik', 'Organoleptik', 'SNI 2346:2015', 'Minimal 7,0'),

            $p('A', 'Angka Lempeng Total (ALT) Aerob', 'Biologi', 'Mikrobiologi', 'SNI 2332.3-2015', 'Maks. 5,0 x 10⁵ kol/g'),
            $p('E1', 'Escherichia coli', 'Biologi', 'Mikrobiologi', 'ISO 16649-3-2015', 'Negatif/25 g'),
            $p('E2', 'Escherichia coli (APM)', 'Biologi', 'Mikrobiologi', 'SNI 2332.1-2015', '< 3 APM/g'),
            $p('C', 'Coliform', 'Biologi', 'Mikrobiologi', 'SNI 2332.1-2015', '< 3 APM/g'),
            $p('S', 'Salmonella spp.', 'Biologi', 'Mikrobiologi', 'SNI ISO 6579-1:2017', 'Negatif/25 g'),
            $p('VP', 'Vibrio parahaemolyticus', 'Biologi', 'Mikrobiologi', 'SNI 01-2332.5-2006', '< 3,0 APM/g'),
            $p('VC', 'Vibrio cholerae', 'Biologi', 'Mikrobiologi', 'SNI 01-2332.4-2006', 'Negatif/25 g'),
            $p('SA', 'Staphylococcus aureus', 'Biologi', 'Mikrobiologi', 'SNI 2332.9-2015', 'Maks. 1,0 x 10³ kol/g'),
            $p('ENTM', 'Enterococci', 'Biologi', 'Mikrobiologi', 'SNI ISO 7899-2-2010', '0 kol/100 ml'),
            $p('CM', 'Coliform (membran filter)', 'Biologi', 'Mikrobiologi', 'ISO 9308-1:2014 / Amd.1:2016(E)', '0 kol/100 ml'),
            $p('ECM', 'Escherichia coli (membran filter)', 'Biologi', 'Mikrobiologi', 'ISO 9308-1:2014 / Amd.1:2016(E)', '0 kol/100 ml'),
            $p('LM', 'Listeria monocytogenes', 'Biologi', 'Mikrobiologi', '-', 'Negatif/25 g', null, false),

            $p('F', 'Formalin', 'Kimia', 'Kimia', 'IKM/OK/LP/SUB II/6', 'Negatif', 'LoD 1,5 mg/kg'),
            $p('H', 'Histamin', 'Kimia', 'Kimia', 'ISO 19343:2017', 'MRL 100 mg/kg', 'LoD 6,03 mg/kg LoQ 19,91 mg/kg'),
            $p('CAP', 'Chloramphenicol', 'Kimia', 'Kimia', 'IKM/OK/LP/SUB II/18', 'RPA 0,15 µg/kg', 'CCβ 0,075 µg/kg'),
            $p('AOZ', 'AOZ', 'Kimia', 'Kimia', 'IKM/OK/LP/SUB II/12', 'RPA 0,5 µg/kg', 'CCβ 0,25 µg/kg'),
            $p('AMOZ', 'AMOZ', 'Kimia', 'Kimia', 'IKM/OK/LP/SUB II/13', 'RPA 0,5 µg/kg', 'CCβ 0,25 µg/kg'),
            $p('AHD', 'AHD', 'Kimia', 'Kimia', 'ELISA', 'RPA 0,5 µg/kg'),
            $p('SEM', 'SEM', 'Kimia', 'Kimia', 'ELISA', 'RPA 0,5 µg/kg'),
            $p('HG', 'Merkuri (Hg)', 'Kimia', 'Kimia', 'SNI 2354.6:2016', 'Maks. 0,5 mg/kg', 'LoD 0,03 mg/kg LoQ 0,05 mg/kg', false),
            $p('PB', 'Timbal (Pb)', 'Kimia', 'Kimia', 'SNI 2354-5:2011 (Konfirmasi 2021)', 'Maks. 0,3 mg/kg', 'LoD 0,01 mg/kg LoQ 0,02 mg/kg', false),
            $p('CD', 'Cadmium (Cd)', 'Kimia', 'Kimia', 'SNI 2354-5:2011 (Konfirmasi 2021)', 'Maks. 0,1 mg/kg', 'LoD 0,005 mg/kg LoQ 0,006 mg/kg', false),
            $p('KA', 'Kadar air', 'Kimia', 'Kimia', '-', null, null, false),
            $p('KG', 'Kadar garam', 'Kimia', 'Kimia', '-', null, null, false),

            $p('CS', 'Cesium (Cs-137)', 'Radionuklida', 'Radionuklida', 'IKM/OK/LP/SUB II/1 (Spektro Gamma HPGe)', '< 8 Bq/kg (LoQ USFDA)', null, false),
        ];
    }
}
