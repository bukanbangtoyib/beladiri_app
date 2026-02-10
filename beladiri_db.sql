-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 01, 2026 at 03:51 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `beladiri_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `anggota`
--

CREATE TABLE `anggota` (
  `id` int(11) NOT NULL,
  `no_anggota` varchar(20) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `tempat_lahir` varchar(100) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `jenis_kelamin` enum('L','P') DEFAULT NULL,
  `ranting_awal_id` int(11) DEFAULT NULL,
  `ranting_awal_manual` varchar(100) NOT NULL,
  `ranting_saat_ini_id` int(11) DEFAULT NULL,
  `tingkat_id` int(11) DEFAULT NULL,
  `jenis_anggota` enum('murid','pelatih','pelatih_unit') NOT NULL,
  `tahun_bergabung` int(4) DEFAULT NULL,
  `no_handphone` varchar(20) DEFAULT NULL,
  `foto` longblob DEFAULT NULL,
  `nama_foto` varchar(255) DEFAULT NULL,
  `status_kerohanian` enum('belum','sudah') DEFAULT 'belum',
  `tanggal_pembukaan_kerohanian` date DEFAULT NULL,
  `ukt_terakhir` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `anggota`
--

INSERT INTO `anggota` (`id`, `no_anggota`, `nama_lengkap`, `tempat_lahir`, `tanggal_lahir`, `jenis_kelamin`, `ranting_awal_id`, `ranting_awal_manual`, `ranting_saat_ini_id`, `tingkat_id`, `jenis_anggota`, `tahun_bergabung`, `no_handphone`, `foto`, `nama_foto`, `status_kerohanian`, `tanggal_pembukaan_kerohanian`, `ukt_terakhir`, `created_at`, `updated_at`) VALUES
(2, '12346', 'Bukan Bang Toyib', 'Zimbabwe', '1989-09-15', 'L', NULL, '', 1, 11, '', NULL, NULL, NULL, '12346', 'sudah', '2023-08-17', NULL, '2026-01-06 08:12:02', '2026-01-07 04:01:56'),
(3, '12347', 'son Go ku', 'jepang', '1985-06-05', 'L', 1, '', 1, 13, '', NULL, NULL, NULL, '12347', 'belum', NULL, '2026-02-01', '2026-01-06 08:14:35', '2026-02-01 12:08:00'),
(4, '12345', 'Bang Toyib', 'Surabaya', '1988-05-29', 'L', 1, '', 1, 10, '', NULL, '0', NULL, '12345', 'belum', NULL, '2026-02-01', '2026-01-06 08:32:17', '2026-02-01 12:08:15'),
(5, '12348', 'Ten Shin Han', 'Makasar', '2000-08-16', 'L', NULL, '', 4, 9, '', NULL, NULL, NULL, '12348_Ten_Shin_Han.png', 'belum', NULL, '2026-02-01', '2026-01-12 07:34:30', '2026-02-01 12:08:15');

-- --------------------------------------------------------

--
-- Table structure for table `jadwal_latihan`
--

CREATE TABLE `jadwal_latihan` (
  `id` int(11) NOT NULL,
  `ranting_id` int(11) NOT NULL,
  `hari` varchar(20) DEFAULT NULL,
  `jam_mulai` time DEFAULT NULL,
  `jam_selesai` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kerohanian`
--

CREATE TABLE `kerohanian` (
  `id` int(11) NOT NULL,
  `anggota_id` int(11) NOT NULL,
  `ranting_id` int(11) DEFAULT NULL,
  `tanggal_pembukaan` date NOT NULL,
  `lokasi` varchar(150) DEFAULT NULL,
  `pembuka_nama` varchar(100) DEFAULT NULL,
  `penyelenggara` varchar(100) DEFAULT NULL,
  `tingkat_pembuka_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `kerohanian`
--

INSERT INTO `kerohanian` (`id`, `anggota_id`, `ranting_id`, `tanggal_pembukaan`, `lokasi`, `pembuka_nama`, `penyelenggara`, `tingkat_pembuka_id`, `created_at`) VALUES
(1, 2, 1, '2023-08-17', 'TL', 'Manari', 'Pengkot Surabaya', 13, '2026-01-07 04:01:56');

-- --------------------------------------------------------

--
-- Table structure for table `pengurus`
--

CREATE TABLE `pengurus` (
  `id` int(11) NOT NULL,
  `jenis_pengurus` enum('pusat','provinsi','kota') NOT NULL,
  `nama_pengurus` varchar(100) NOT NULL,
  `sk_kepengurusan` varchar(50) DEFAULT NULL,
  `periode_mulai` date DEFAULT NULL,
  `periode_akhir` date DEFAULT NULL,
  `ketua_nama` varchar(100) DEFAULT NULL,
  `alamat_sekretariat` text DEFAULT NULL,
  `pengurus_induk_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `pengurus`
--

INSERT INTO `pengurus` (`id`, `jenis_pengurus`, `nama_pengurus`, `sk_kepengurusan`, `periode_mulai`, `periode_akhir`, `ketua_nama`, `alamat_sekretariat`, `pengurus_induk_id`, `created_at`) VALUES
(191, 'pusat', 'PP Indonesia', '123/Sk/PP/2022', '2022-02-19', '2026-02-19', 'Dwi S', 'Karimata', NULL, '2026-01-24 15:40:49'),
(192, 'provinsi', 'Aceh', 'SK/001/PP-ID/2024', '2024-05-12', '2029-05-12', 'Bambang Hermanto', 'Jl. Merdeka No. 10 Banda Aceh', 191, '2026-01-24 15:41:04'),
(193, 'provinsi', 'Sumatera Utara', 'SK/002/PP-ID/2024', '2024-01-20', '2029-01-20', 'Siti Aminah', 'Jl. Diponegoro No. 45 Medan', 191, '2026-01-24 15:41:04'),
(194, 'provinsi', 'Sumatera Barat', 'SK/003/PP-ID/2024', '2024-03-15', '2029-03-15', 'Andi Wijaya', 'Jl. Sudirman No. 22 Padang', 191, '2026-01-24 15:41:04'),
(195, 'provinsi', 'Riau', 'SK/004/PP-ID/2024', '2024-02-10', '2029-02-10', 'Budi Santoso', 'Jl. Gajah Mada No. 8 Pekanbaru', 191, '2026-01-24 15:41:04'),
(196, 'provinsi', 'Kepulauan Riau', 'SK/005/PP-ID/2024', '2024-06-05', '2029-06-05', 'Dewi Lestari', 'Jl. Basuki Rahmat No. 12 Tanjungpinang', 191, '2026-01-24 15:41:04'),
(197, 'provinsi', 'Jambi', 'SK/006/PP-ID/2024', '2024-04-22', '2029-04-22', 'Eko Prasetyo', 'Jl. Jend. Gatot Subroto No. 30 Jambi', 191, '2026-01-24 15:41:04'),
(198, 'provinsi', 'Bengkulu', 'SK/007/PP-ID/2024', '2024-07-18', '2029-07-18', 'Fajar Siddiq', 'Jl. Pembangunan No. 5 Bengkulu', 191, '2026-01-24 15:41:04'),
(199, 'provinsi', 'Sumatera Selatan', 'SK/008/PP-ID/2024', '2024-08-09', '2029-08-09', 'Gita Permata', 'Jl. Kapten Rivai No. 100 Palembang', 191, '2026-01-24 15:41:04'),
(200, 'provinsi', 'Kepulauan Bangka Belitung', 'SK/009/PP-ID/2024', '2024-03-30', '2029-03-30', 'Hadi Kusuma', 'Jl. Ahmad Yani No. 15 Pangkalpinang', 191, '2026-01-24 15:41:04'),
(201, 'provinsi', 'Lampung', 'SK/010/PP-ID/2024', '2024-01-14', '2029-01-14', 'Indra Jaya', 'Jl. Cut Nyak Dien No. 7 Bandar Lampung', 191, '2026-01-24 15:41:04'),
(202, 'provinsi', 'DKI Jakarta', 'SK/011/PP-ID/2024', '2024-11-11', '2029-11-11', 'Joko Susilo', 'Jl. Medan Merdeka Selatan No. 8 Jakarta', 191, '2026-01-24 15:41:04'),
(203, 'provinsi', 'Jawa Barat', 'SK/012/PP-ID/2024', '2024-05-25', '2029-05-25', 'Kiki Amelia', 'Jl. Diponegoro No. 22 Bandung', 191, '2026-01-24 15:41:04'),
(204, 'provinsi', 'Banten', 'SK/013/PP-ID/2024', '2024-09-02', '2029-09-02', 'Lutfi Hakim', 'Jl. Syekh Nawawi Al-Bantani Serang', 191, '2026-01-24 15:41:04'),
(205, 'provinsi', 'Jawa Tengah', 'SK/014/PP-ID/2024', '2024-10-17', '2029-10-17', 'Mulyadi', 'Jl. Pahlawan No. 9 Semarang', 191, '2026-01-24 15:41:04'),
(206, 'provinsi', 'DI Yogyakarta', 'SK/015/PP-ID/2024', '2024-12-08', '2029-12-08', 'Novi Fitriani', 'Jl. Malioboro No. 16 Yogyakarta', 191, '2026-01-24 15:41:04'),
(207, 'provinsi', 'Jawa Timur', 'SK/016/PP-ID/2024', '2024-02-13', '2029-02-13', 'Oki Setiawan', 'Jl. Gubernur Suryo No. 7 Surabaya', 191, '2026-01-24 15:41:04'),
(208, 'provinsi', 'Bali', 'SK/017/PP-ID/2024', '2024-03-21', '2029-03-21', 'Putu Gede', 'Jl. Niti Mandala No. 1 Denpasar', 191, '2026-01-24 15:41:04'),
(209, 'provinsi', 'Nusa Tenggara Barat', 'SK/018/PP-ID/2024', '2024-06-28', '2029-06-28', 'Rina Marlina', 'Jl. Pejanggik No. 12 Mataram', 191, '2026-01-24 15:41:04'),
(210, 'provinsi', 'Nusa Tenggara Timur', 'SK/019/PP-ID/2024', '2024-04-19', '2029-04-19', 'Samuel K.', 'Jl. El Tari No. 52 Kupang', 191, '2026-01-24 15:41:04'),
(211, 'provinsi', 'Kalimantan Barat', 'SK/020/PP-ID/2024', '2024-07-04', '2029-07-04', 'Taufik Hidayat', 'Jl. Ahmad Yani No. 1 Pontianak', 191, '2026-01-24 15:41:04'),
(213, 'kota', 'Lhokseumawe', 'SK/KOTA/002/2024', '2024-02-15', '2029-02-15', 'Budi Santoso', 'Jl. Merdeka No. 45', 192, '2026-01-24 15:41:14'),
(214, 'kota', 'Langsa', 'SK/KOTA/003/2024', '2024-03-20', '2029-03-20', 'Siti Aminah', 'Jl. Ahmad Yani No. 12', 192, '2026-01-24 15:41:14'),
(215, 'kota', 'Meulaboh', 'SK/KOTA/004/2024', '2024-04-10', '2029-04-10', 'Andi Wijaya', 'Jl. Teuku Umar No. 8', 192, '2026-01-24 15:41:14'),
(216, 'kota', 'Sabang', 'SK/KOTA/005/2024', '2024-05-05', '2029-05-05', 'Dewi Lestari', 'Jl. Perdagangan No. 3', 192, '2026-01-24 15:41:14'),
(217, 'kota', 'Medan', 'SK/KOTA/006/2024', '2024-01-18', '2029-01-18', 'Eko Prasetyo', 'Jl. Balai Kota No. 1', 193, '2026-01-24 15:41:14'),
(218, 'kota', 'Binjai', 'SK/KOTA/007/2024', '2024-02-22', '2029-02-22', 'Fajar Siddiq', 'Jl. Jend. Sudirman No. 10', 193, '2026-01-24 15:41:14'),
(219, 'kota', 'Pematangsiantar', 'SK/KOTA/008/2024', '2024-03-09', '2029-03-09', 'Gita Permata', 'Jl. Merdeka No. 56', 193, '2026-01-24 15:41:14'),
(220, 'kota', 'Tebing Tinggi', 'SK/KOTA/009/2024', '2024-04-14', '2029-04-14', 'Hadi Kusuma', 'Jl. Sutomo No. 22', 193, '2026-01-24 15:41:14'),
(221, 'kota', 'Padang Sidempuan', 'SK/KOTA/010/2024', '2024-05-25', '2029-05-25', 'Indra Jaya', 'Jl. Sudirman No. 5', 193, '2026-01-24 15:41:14'),
(222, 'kota', 'Padang', 'SK/KOTA/011/2024', '2024-01-11', '2029-01-11', 'Joko Susilo', 'Jl. Bagindo Aziz No. 1', 194, '2026-01-24 15:41:14'),
(223, 'kota', 'Bukittinggi', 'SK/KOTA/012/2024', '2024-02-07', '2029-02-07', 'Kiki Amelia', 'Jl. Panorama No. 15', 194, '2026-01-24 15:41:14'),
(224, 'kota', 'Payakumbuh', 'SK/KOTA/013/2024', '2024-03-19', '2029-03-19', 'Lutfi Hakim', 'Jl. Soekarno Hatta No. 4', 194, '2026-01-24 15:41:14'),
(225, 'kota', 'Pariaman', 'SK/KOTA/014/2024', '2024-04-30', '2029-04-30', 'Mulyadi', 'Jl. Imam Bonjol No. 9', 194, '2026-01-24 15:41:14'),
(226, 'kota', 'Solok', 'SK/KOTA/015/2024', '2024-05-14', '2029-05-14', 'Novi Fitriani', 'Jl. Lubuk Sikarah No. 2', 194, '2026-01-24 15:41:14'),
(227, 'kota', 'Pekanbaru', 'SK/KOTA/016/2024', '2024-01-02', '2029-01-02', 'Oki Setiawan', 'Jl. Jend. Sudirman No. 100', 195, '2026-01-24 15:41:14'),
(228, 'kota', 'Dumai', 'SK/KOTA/017/2024', '2024-02-16', '2029-02-16', 'Putu Gede', 'Jl. Putri Tujuh No. 1', 195, '2026-01-24 15:41:14'),
(229, 'kota', 'Siak', 'SK/KOTA/018/2024', '2024-03-21', '2029-03-21', 'Rina Marlina', 'Jl. Raja Kecik No. 44', 195, '2026-01-24 15:41:14'),
(230, 'kota', 'Bengkalis', 'SK/KOTA/019/2024', '2024-04-05', '2029-04-05', 'Samuel K.', 'Jl. Ahmad Yani No. 5', 195, '2026-01-24 15:41:14'),
(231, 'kota', 'Kampar', 'SK/KOTA/020/2024', '2024-05-12', '2029-05-12', 'Taufik Hidayat', 'Jl. Prof. M. Yamin No. 1', 195, '2026-01-24 15:41:14'),
(232, 'kota', 'Tanjungpinang', 'SK/KOTA/021/2024', '2024-01-08', '2029-01-08', 'Agus Salim', 'Jl. Basuki Rahmat No. 1', 196, '2026-01-24 15:41:14'),
(233, 'kota', 'Batam', 'SK/KOTA/022/2024', '2024-02-23', '2029-02-23', 'Anisa Putri', 'Jl. Engku Putri No. 1', 196, '2026-01-24 15:41:14'),
(234, 'kota', 'Bintan', 'SK/KOTA/023/2024', '2024-03-11', '2029-03-11', 'Bambang Sugeng', 'Jl. Tata Bumi No. 7', 196, '2026-01-24 15:41:14'),
(235, 'kota', 'Karimun', 'SK/KOTA/024/2024', '2024-04-27', '2029-04-27', 'Candra Wijaya', 'Jl. Jend. Sudirman No. 88', 196, '2026-01-24 15:41:14'),
(236, 'kota', 'Natuna', 'SK/KOTA/025/2024', '2024-05-09', '2029-05-09', 'Dian Sastro', 'Jl. Batu Sisir No. 10', 196, '2026-01-24 15:41:14'),
(237, 'kota', 'Jambi', 'SK/KOTA/026/2024', '2024-01-04', '2029-01-04', 'Edi Rahmayadi', 'Jl. Balaikota No. 1', 197, '2026-01-24 15:41:14'),
(238, 'kota', 'Sungai Penuh', 'SK/KOTA/027/2024', '2024-02-17', '2029-02-17', 'Farah Quinn', 'Jl. Muradi No. 5', 197, '2026-01-24 15:41:14'),
(239, 'kota', 'Muaro Jambi', 'SK/KOTA/028/2024', '2024-03-29', '2029-03-29', 'Gunawan W.', 'Jl. Lintas Timur No. 12', 197, '2026-01-24 15:41:14'),
(240, 'kota', 'Bungo', 'SK/KOTA/029/2024', '2024-04-13', '2029-04-13', 'Hendra Setiawan', 'Jl. RM Thaher No. 2', 197, '2026-01-24 15:41:14'),
(241, 'kota', 'Tebo', 'SK/KOTA/030/2024', '2024-05-26', '2029-05-26', 'Irfan Hakim', 'Jl. Lintas Sumatera Km 12', 197, '2026-01-24 15:41:14'),
(242, 'kota', 'Bengkulu', 'SK/KOTA/031/2024', '2024-01-10', '2029-01-10', 'Jessica Mila', 'Jl. Basuki Rahmat No. 5', 198, '2026-01-24 15:41:14'),
(243, 'kota', 'Rejang Lebong', 'SK/KOTA/032/2024', '2024-02-24', '2029-02-24', 'Kevin Sanjaya', 'Jl. Sukowati No. 1', 198, '2026-01-24 15:41:14'),
(244, 'kota', 'Muko Muko', 'SK/KOTA/033/2024', '2024-03-06', '2029-03-06', 'Lesti Kejora', 'Jl. Jend. Sudirman No. 3', 198, '2026-01-24 15:41:14'),
(245, 'kota', 'Kepahiang', 'SK/KOTA/034/2024', '2024-04-18', '2029-04-18', 'Maia Estianty', 'Jl. Pembangunan No. 8', 198, '2026-01-24 15:41:14'),
(246, 'kota', 'Lebong', 'SK/KOTA/035/2024', '2024-05-30', '2029-05-30', 'Nadiem Makarim', 'Jl. Raya Muara Aman', 198, '2026-01-24 15:41:14'),
(247, 'kota', 'Palembang', 'SK/KOTA/036/2024', '2024-01-03', '2029-01-03', 'Onadio Leonardo', 'Jl. Merdeka No. 1', 199, '2026-01-24 15:41:14'),
(248, 'kota', 'Lubuklinggau', 'SK/KOTA/037/2024', '2024-02-12', '2029-02-12', 'Prilly L.', 'Jl. Garuda No. 12', 199, '2026-01-24 15:41:14'),
(249, 'kota', 'Pagar Alam', 'SK/KOTA/038/2024', '2024-03-25', '2029-03-25', 'Qory Sandioriva', 'Jl. Mayor Ruslan No. 4', 199, '2026-01-24 15:41:14'),
(250, 'kota', 'Prabumulih', 'SK/KOTA/039/2024', '2024-04-07', '2029-04-07', 'Raffi Ahmad', 'Jl. Jend. Sudirman No. 9', 199, '2026-01-24 15:41:14'),
(251, 'kota', 'Ogan Ilir', 'SK/KOTA/040/2024', '2024-05-19', '2029-05-19', 'Sule Sutisna', 'Jl. Lintas Timur Km 32', 199, '2026-01-24 15:41:14'),
(252, 'kota', 'Pangkalpinang', 'SK/KOTA/041/2024', '2024-01-01', '2029-01-01', 'Tora Sudiro', 'Jl. Rasakunda No. 1', 200, '2026-01-24 15:41:14'),
(253, 'kota', 'Sungailiat', 'SK/KOTA/042/2024', '2024-02-14', '2029-02-14', 'Uus Biasa', 'Jl. Ahmad Yani No. 10', 200, '2026-01-24 15:41:14'),
(254, 'kota', 'Tanjung Pandan', 'SK/KOTA/043/2024', '2024-03-26', '2029-03-26', 'Vicky Prasetyo', 'Jl. Depati Amir No. 5', 200, '2026-01-24 15:41:14'),
(255, 'kota', 'Muntok', 'SK/KOTA/044/2024', '2024-04-08', '2029-04-08', 'Wanda Hamidah', 'Jl. Jend. Sudirman No. 33', 200, '2026-01-24 15:41:14'),
(256, 'kota', 'Toboali', 'SK/KOTA/045/2024', '2024-05-20', '2029-05-20', 'Xena Zen', 'Jl. Sudirman No. 7', 200, '2026-01-24 15:41:14'),
(257, 'kota', 'Bandar Lampung', 'SK/KOTA/046/2024', '2024-01-05', '2029-01-05', 'Yuni Shara', 'Jl. Dr. Susilo No. 2', 201, '2026-01-24 15:41:14'),
(258, 'kota', 'Metro', 'SK/KOTA/047/2024', '2024-02-18', '2029-02-18', 'Zaskia Adya', 'Jl. AH Nasution No. 1', 201, '2026-01-24 15:41:14'),
(259, 'kota', 'Pringsewu', 'SK/KOTA/048/2024', '2024-03-02', '2029-03-02', 'Abdul Somad', 'Jl. Jend. Sudirman No. 45', 201, '2026-01-24 15:41:14'),
(260, 'kota', 'Liwa', 'SK/KOTA/049/2024', '2024-04-14', '2029-04-14', 'Baim Wong', 'Jl. Raden Intan No. 8', 201, '2026-01-24 15:41:14'),
(261, 'kota', 'Kalianda', 'SK/KOTA/050/2024', '2024-05-26', '2029-05-26', 'Cinta Laura', 'Jl. Kesuma Bangsa No. 1', 201, '2026-01-24 15:41:14'),
(262, 'kota', 'Jakarta Pusat', 'SK/KOTA/051/2024', '2024-01-01', '2029-01-01', 'Desta Mahendra', 'Jl. Tanah Abang I No. 1', 202, '2026-01-24 15:41:14'),
(263, 'kota', 'Jakarta Selatan', 'SK/KOTA/052/2024', '2024-02-12', '2029-02-12', 'Ernest Prakasa', 'Jl. Prapanca Raya No. 9', 202, '2026-01-24 15:41:14'),
(264, 'kota', 'Jakarta Timur', 'SK/KOTA/053/2024', '2024-03-23', '2029-03-23', 'Fiersa Besari', 'Jl. Dr. Sumarno No. 1', 202, '2026-01-24 15:41:14'),
(265, 'kota', 'Jakarta Barat', 'SK/KOTA/054/2024', '2024-04-04', '2029-04-04', 'Gading Marten', 'Jl. Raya Kembangan No. 2', 202, '2026-01-24 15:41:14'),
(266, 'kota', 'Jakarta Utara', 'SK/KOTA/055/2024', '2024-05-15', '2029-05-15', 'Hamish Daud', 'Jl. Yos Sudarso No. 27', 202, '2026-01-24 15:41:14'),
(267, 'kota', 'Bandung', 'SK/KOTA/056/2024', '2024-01-06', '2029-01-06', 'Isyana Sarasvati', 'Jl. Wastukencana No. 2', 203, '2026-01-24 15:41:14'),
(268, 'kota', 'Bogor', 'SK/KOTA/057/2024', '2024-02-17', '2029-02-17', 'Joe Taslim', 'Jl. Ir. H. Juanda No. 10', 203, '2026-01-24 15:41:14'),
(269, 'kota', 'Bekasi', 'SK/KOTA/058/2024', '2024-03-28', '2029-03-28', 'Krisdayanti', 'Jl. Ahmad Yani No. 1', 203, '2026-01-24 15:41:14'),
(270, 'kota', 'Depok', 'SK/KOTA/059/2024', '2024-04-09', '2029-04-09', 'Luna Maya', 'Jl. Margonda Raya No. 54', 203, '2026-01-24 15:41:14'),
(271, 'kota', 'Cimahi', 'SK/KOTA/060/2024', '2024-05-20', '2029-05-20', 'Maudy Ayunda', 'Jl. Rd. Demang Hardjakusumah', 203, '2026-01-24 15:41:14'),
(272, 'kota', 'Serang', 'SK/KOTA/061/2024', '2024-01-02', '2029-01-02', 'Najwa Shihab', 'Jl. Jend. Sudirman No. 155', 204, '2026-01-24 15:41:14'),
(273, 'kota', 'Tangerang', 'SK/KOTA/062/2024', '2024-02-13', '2029-02-13', 'Olla Ramlan', 'Jl. Satria Sudirman No. 1', 204, '2026-01-24 15:41:14'),
(274, 'kota', 'Cilegon', 'SK/KOTA/063/2024', '2024-03-24', '2029-03-24', 'Pevita Pearce', 'Jl. Jend. Sudirman No. 2', 204, '2026-01-24 15:41:14'),
(275, 'kota', 'Tangerang Selatan', 'SK/KOTA/064/2024', '2024-04-05', '2029-04-05', 'Raisa Andriana', 'Jl. Maruga Raya No. 1', 204, '2026-01-24 15:41:14'),
(276, 'kota', 'Pandeglang', 'SK/KOTA/065/2024', '2024-05-17', '2029-05-17', 'Syafiq Riza', 'Jl. Komplek Perkantoran Cikupa', 204, '2026-01-24 15:41:14'),
(277, 'kota', 'Semarang', 'SK/KOTA/066/2024', '2024-01-10', '2029-01-10', 'Tulus', 'Jl. Pemuda No. 148', 205, '2026-01-24 15:41:14'),
(278, 'kota', 'Surakarta', 'SK/KOTA/067/2024', '2024-02-21', '2029-02-21', 'Uus', 'Jl. Jend. Sudirman No. 2', 205, '2026-01-24 15:41:14'),
(279, 'kota', 'Magelang', 'SK/KOTA/068/2024', '2024-03-03', '2029-03-03', 'Vidi Aldiano', 'Jl. Sarwo Edhie Wibowo No. 1', 205, '2026-01-24 15:41:14'),
(280, 'kota', 'Pekalongan', 'SK/KOTA/069/2024', '2024-04-14', '2029-04-14', 'Wira Nagara', 'Jl. Mataram No. 1', 205, '2026-01-24 15:41:14'),
(281, 'kota', 'Salatiga', 'SK/KOTA/070/2024', '2024-05-25', '2029-05-25', 'Yura Yunita', 'Jl. Letjen Sukowati No. 51', 205, '2026-01-24 15:41:14'),
(282, 'kota', 'Yogyakarta', 'SK/KOTA/071/2024', '2024-01-04', '2029-01-04', 'Ziva Magnolya', 'Jl. Kenari No. 56', 206, '2026-01-24 15:41:14'),
(283, 'kota', 'Sleman', 'SK/KOTA/072/2024', '2024-02-15', '2029-02-15', 'Ariel Noah', 'Jl. Parasamya No. 1', 206, '2026-01-24 15:41:14'),
(284, 'kota', 'Bantul', 'SK/KOTA/073/2024', '2024-03-26', '2029-03-26', 'Bunga Citra L.', 'Jl. Robert Wolter Monginsidi', 206, '2026-01-24 15:41:14'),
(285, 'kota', 'Kulon Progo', 'SK/KOTA/074/2024', '2024-04-07', '2029-04-07', 'Chico Jericho', 'Jl. Perwakilan No. 1', 206, '2026-01-24 15:41:14'),
(286, 'kota', 'Gunung Kidul', 'SK/KOTA/075/2024', '2024-05-18', '2029-05-18', 'Dian Sastro', 'Jl. Satria No. 3', 206, '2026-01-24 15:41:14'),
(287, 'kota', 'Surabaya', 'SK/KOTA/076/2024', '2024-01-08', '2029-01-08', 'Eross Candra', 'Jl. Taman Surya No. 1', 207, '2026-01-24 15:41:14'),
(288, 'kota', 'Malang', 'SK/KOTA/077/2024', '2024-02-19', '2029-02-19', 'Ferry Maryadi', 'Jl. Tugu No. 1', 207, '2026-01-24 15:41:14'),
(289, 'kota', 'Batu', 'SK/KOTA/078/2024', '2024-03-01', '2029-03-01', 'Giring Ganesha', 'Jl. Panglima Sudirman No. 50', 207, '2026-01-24 15:41:14'),
(290, 'kota', 'Kediri', 'SK/KOTA/079/2024', '2024-04-12', '2029-04-12', 'Hesti Purwadinata', 'Jl. Jend. Basuki Rahmat No. 15', 207, '2026-01-24 15:41:14'),
(291, 'kota', 'Madiun', 'SK/KOTA/080/2024', '2024-05-23', '2029-05-23', 'Indra Herlambang', 'Jl. Pahlawan No. 37', 207, '2026-01-24 15:41:14'),
(292, 'kota', 'Denpasar', 'SK/KOTA/081/2024', '2024-01-03', '2029-01-03', 'Jerinx SID', 'Jl. Gajah Mada No. 1', 208, '2026-01-24 15:41:14'),
(293, 'kota', 'Singaraja', 'SK/KOTA/082/2024', '2024-02-14', '2029-02-14', 'Kaka Slank', 'Jl. Ngurah Rai No. 74', 208, '2026-01-24 15:41:14'),
(294, 'kota', 'Tabanan', 'SK/KOTA/083/2024', '2024-03-25', '2029-03-25', 'Lulu Tobing', 'Jl. Pahlawan No. 10', 208, '2026-01-24 15:41:14'),
(295, 'kota', 'Gianyar', 'SK/KOTA/084/2024', '2024-04-06', '2029-04-06', 'Melanie Subono', 'Jl. Ciung Wanara No. 1', 208, '2026-01-24 15:41:14'),
(296, 'kota', 'Ubud', 'SK/KOTA/085/2024', '2024-05-17', '2029-05-17', 'Nicholas Saputra', 'Jl. Raya Ubud No. 8', 208, '2026-01-24 15:41:14'),
(297, 'kota', 'Mataram', 'SK/KOTA/086/2024', '2024-01-07', '2029-01-07', 'Once Mekel', 'Jl. Pejanggik No. 16', 209, '2026-01-24 15:41:14'),
(298, 'kota', 'Bima', 'SK/KOTA/087/2024', '2024-02-18', '2029-02-18', 'Pasha Ungu', 'Jl. Soekarno Hatta No. 1', 209, '2026-01-24 15:41:14'),
(299, 'kota', 'Sumbawa', 'SK/KOTA/088/2024', '2024-03-29', '2029-03-29', 'Rizal Armada', 'Jl. Garuda No. 100', 209, '2026-01-24 15:41:14'),
(300, 'kota', 'Lombok Barat', 'SK/KOTA/089/2024', '2024-04-10', '2029-04-10', 'Sheila Dara', 'Jl. Soekarno Hatta Giri Menang', 209, '2026-01-24 15:41:14'),
(301, 'kota', 'Lombok Timur', 'SK/KOTA/090/2024', '2024-05-22', '2029-05-22', 'Tora Sudiro', 'Jl. Ahmad Yani No. 1', 209, '2026-01-24 15:41:14'),
(302, 'kota', 'Kupang', 'SK/KOTA/091/2024', '2024-01-01', '2029-01-01', 'Uut Permatasari', 'Jl. S.K. Lerik No. 1', 210, '2026-01-24 15:41:14'),
(303, 'kota', 'Ende', 'SK/KOTA/092/2024', '2024-02-12', '2029-02-12', 'Vino G. Bastian', 'Jl. El Tari No. 5', 210, '2026-01-24 15:41:14'),
(304, 'kota', 'Maumere', 'SK/KOTA/093/2024', '2024-03-23', '2029-03-23', 'Wulan Guritno', 'Jl. Ahmad Yani No. 12', 210, '2026-01-24 15:41:14'),
(305, 'kota', 'Ruteng', 'SK/KOTA/094/2024', '2024-04-04', '2029-04-04', 'Yayan Ruhian', 'Jl. Motang Rua No. 1', 210, '2026-01-24 15:41:14'),
(306, 'kota', 'Labuan Bajo', 'SK/KOTA/095/2024', '2024-05-15', '2029-05-15', 'Zulhuir', 'Jl. Soekarno Hatta', 210, '2026-01-24 15:41:14'),
(307, 'kota', 'Pontianak', 'SK/KOTA/096/2024', '2024-01-05', '2029-01-05', 'Ahmad Dhani', 'Jl. Rahadi Usman No. 3', 211, '2026-01-24 15:41:14'),
(308, 'kota', 'Singkawang', 'SK/KOTA/097/2024', '2024-02-16', '2029-02-16', 'Baim Wong', 'Jl. Firdaus No. 1', 211, '2026-01-24 15:41:14'),
(309, 'kota', 'Sintang', 'SK/KOTA/098/2024', '2024-03-27', '2029-03-27', 'Coki Pardede', 'Jl. Jend. Sudirman No. 9', 211, '2026-01-24 15:41:14'),
(310, 'kota', 'Ketapang', 'SK/KOTA/099/2024', '2024-04-08', '2029-04-08', 'Desta Mahendra', 'Jl. Jend. Urip Sumoharjo No. 1', 211, '2026-01-24 15:41:14'),
(311, 'kota', 'Sambas', 'SK/KOTA/100/2024', '2024-05-20', '2029-05-20', 'Eki Pitung', 'Jl. Pembangunan No. 2', 211, '2026-01-24 15:41:14'),
(312, 'provinsi', 'Zimbabwe', '1548579', '2021-07-30', '2026-03-31', 'Inuyasha', 'Zimbabwe gang buntu', 191, '2026-01-30 04:14:32'),
(313, 'kota', 'Oloko', '789654/78', '2023-06-30', '2026-02-26', 'Masehi', 'Nang Kene Lo', 312, '2026-01-30 04:15:41');

-- --------------------------------------------------------

--
-- Table structure for table `prestasi`
--

CREATE TABLE `prestasi` (
  `id` int(11) NOT NULL,
  `anggota_id` int(11) NOT NULL,
  `event_name` varchar(255) NOT NULL COMMENT 'Nama event/lomba',
  `tanggal_pelaksanaan` date NOT NULL COMMENT 'Tanggal pelaksanaan event',
  `penyelenggara` varchar(150) DEFAULT NULL COMMENT 'Lembaga/organisasi penyelenggara',
  `kategori` varchar(100) DEFAULT NULL COMMENT 'Kategori yang diikuti (misal: Putra -60kg)',
  `prestasi` varchar(100) DEFAULT NULL COMMENT 'Prestasi yang diraih (misal: Juara 1)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ranting`
--

CREATE TABLE `ranting` (
  `id` int(11) NOT NULL,
  `nama_ranting` varchar(100) NOT NULL,
  `jenis` enum('ukm','ranting','unit') NOT NULL,
  `tanggal_sk_pembentukan` date DEFAULT NULL,
  `no_sk_pembentukan` varchar(50) DEFAULT NULL,
  `sk_pembentukan` longblob DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `ketua_nama` varchar(100) DEFAULT NULL,
  `penanggung_jawab_teknik` varchar(100) DEFAULT NULL,
  `no_kontak` varchar(20) DEFAULT NULL,
  `pengurus_kota_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `ranting`
--

INSERT INTO `ranting` (`id`, `nama_ranting`, `jenis`, `tanggal_sk_pembentukan`, `no_sk_pembentukan`, `sk_pembentukan`, `alamat`, `ketua_nama`, `penanggung_jawab_teknik`, `no_kontak`, `pengurus_kota_id`, `created_at`) VALUES
(1, 'TL', 'ranting', '1990-02-25', NULL, NULL, 'Tenggilis Lama', 'Dwi P', 'Prima', '1245789', 287, '2026-01-06 03:22:54'),
(2, 'Gubeng', 'ranting', '2015-06-15', NULL, NULL, 'Gubeng Kertajawa', 'Firman', 'Firman', '089654789632', 287, '2026-01-07 01:03:47'),
(4, 'SMP 1', 'unit', '1990-06-15', NULL, NULL, 'Pacar', 'widodo', 'gatot', '89654789632', 296, '2026-01-07 03:54:55');

-- --------------------------------------------------------

--
-- Table structure for table `ranting_pelatih`
--

CREATE TABLE `ranting_pelatih` (
  `id` int(11) NOT NULL,
  `ranting_id` int(11) NOT NULL,
  `pelatih_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tingkatan`
--

CREATE TABLE `tingkatan` (
  `id` int(11) NOT NULL,
  `nama_tingkat` varchar(50) NOT NULL,
  `urutan` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `tingkatan`
--

INSERT INTO `tingkatan` (`id`, `nama_tingkat`, `urutan`, `created_at`) VALUES
(1, 'Dasar I', 1, '2026-01-06 04:03:41'),
(2, 'Dasar II', 2, '2026-01-06 04:03:41'),
(3, 'Calon Keluarga', 3, '2026-01-06 04:04:15'),
(4, 'Putih', 4, '2026-01-06 04:04:15'),
(5, 'Putih Hijau', 5, '2026-01-06 04:05:54'),
(6, 'Hijau', 6, '2026-01-06 04:05:54'),
(7, 'Hijau Biru', 7, '2026-01-06 04:06:17'),
(8, 'Biru', 8, '2026-01-06 04:06:17'),
(9, 'Biru Merah', 9, '2026-01-06 04:06:45'),
(10, 'Merah', 10, '2026-01-06 04:06:45'),
(11, 'Merah Kuning', 11, '2026-01-06 04:07:09'),
(12, 'Kuning', 12, '2026-01-06 04:07:09'),
(13, 'Pendekar', 13, '2026-01-06 04:07:20');

-- --------------------------------------------------------

--
-- Table structure for table `ukt`
--

CREATE TABLE `ukt` (
  `id` int(11) NOT NULL,
  `tanggal_pelaksanaan` date NOT NULL,
  `lokasi` varchar(150) DEFAULT NULL,
  `penyelenggara_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `sk_kepengurusan` longblob DEFAULT NULL,
  `jumlah_peserta_total` int(11) DEFAULT NULL,
  `sk_kelulusan` longblob DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `ukt`
--

INSERT INTO `ukt` (`id`, `tanggal_pelaksanaan`, `lokasi`, `penyelenggara_id`, `created_by`, `updated_by`, `catatan`, `sk_kepengurusan`, `jumlah_peserta_total`, `sk_kelulusan`, `created_at`) VALUES
(4, '2026-08-23', 'TL', 209, NULL, NULL, 'TEs', NULL, NULL, NULL, '2026-01-07 04:11:32'),
(5, '2026-01-25', 'SMP Muhammadiyah 5', 287, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-28 04:23:52'),
(6, '2026-02-25', 'Tenkaici Budokai', 313, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-30 04:17:53'),
(7, '2025-12-26', 'Karimata', 206, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-31 13:57:57');

-- --------------------------------------------------------

--
-- Table structure for table `ukt_peserta`
--

CREATE TABLE `ukt_peserta` (
  `id` int(11) NOT NULL,
  `ukt_id` int(11) NOT NULL,
  `anggota_id` int(11) NOT NULL,
  `tingkat_dari_id` int(11) DEFAULT NULL,
  `tingkat_ke_id` int(11) DEFAULT NULL,
  `nilai` decimal(5,2) DEFAULT NULL,
  `sertifikat_path` varchar(255) DEFAULT NULL,
  `status` enum('lulus','tidak_lulus','peserta') DEFAULT 'peserta',
  `sertifikat` longblob DEFAULT NULL,
  `nama_sertifikat` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `nilai_a` double DEFAULT NULL,
  `nilai_b` double DEFAULT NULL,
  `nilai_c` double DEFAULT NULL,
  `nilai_d` double DEFAULT NULL,
  `nilai_e` double DEFAULT NULL,
  `nilai_f` double DEFAULT NULL,
  `nilai_g` double DEFAULT NULL,
  `nilai_h` double DEFAULT NULL,
  `nilai_i` double DEFAULT NULL,
  `nilai_j` double DEFAULT NULL,
  `rata_rata` double DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `ukt_peserta`
--

INSERT INTO `ukt_peserta` (`id`, `ukt_id`, `anggota_id`, `tingkat_dari_id`, `tingkat_ke_id`, `nilai`, `sertifikat_path`, `status`, `sertifikat`, `nama_sertifikat`, `created_at`, `nilai_a`, `nilai_b`, `nilai_c`, `nilai_d`, `nilai_e`, `nilai_f`, `nilai_g`, `nilai_h`, `nilai_i`, `nilai_j`, `rata_rata`) VALUES
(6, 4, 4, 6, 7, NULL, 'Sert-UKT-23082026-Bang_Toyib.pdf', 'lulus', NULL, NULL, '2026-01-07 12:20:41', 60, 60, 60, 65, 62, 61, NULL, NULL, NULL, NULL, 61.333333333333336),
(7, 4, 2, 11, 12, NULL, NULL, 'tidak_lulus', NULL, NULL, '2026-01-07 12:20:46', 55, 60, 65, 62, 58, 50, NULL, NULL, NULL, NULL, 58.333333333333336),
(8, 4, 3, 12, 13, NULL, NULL, 'lulus', NULL, NULL, '2026-01-07 12:20:51', 75, 50, 60, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 61.666666666666664),
(9, 4, 5, 7, 8, NULL, 'Sert-UKT-23082026-Ten_Shin_Han.pdf', 'lulus', NULL, NULL, '2026-01-31 16:35:09', 60, 60, 65, 60, 55, 65, NULL, NULL, NULL, NULL, 60.833333333333336),
(10, 6, 5, 7, 8, NULL, NULL, 'peserta', NULL, NULL, '2026-02-01 12:07:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `role` enum('admin','pengprov','pengkot','unit','tamu') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `pengurus_id` int(11) DEFAULT NULL,
  `ranting_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `role`, `created_at`, `pengurus_id`, `ranting_id`) VALUES
(1, 'admin', '$2y$10$4gWkuaIPDc4KoSfJFB.i9uFsPxWQ9VKxrWAyBeR9sb4xTQiuRjLpe', 'Administrator', 'admin', '2026-01-05 08:27:51', NULL, NULL),
(6, 'TL', '$2y$10$ES3Jmfb5mAkHVIpJcfEmVOGDSjSZfb9ZaAPYnOiDguUToXLQjeAj2', 'Admin Tenggilis', 'unit', '2026-01-14 11:02:15', NULL, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `anggota`
--
ALTER TABLE `anggota`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_anggota` (`no_anggota`),
  ADD KEY `ranting_awal_id` (`ranting_awal_id`),
  ADD KEY `ranting_saat_ini_id` (`ranting_saat_ini_id`),
  ADD KEY `tingkat_id` (`tingkat_id`);

--
-- Indexes for table `jadwal_latihan`
--
ALTER TABLE `jadwal_latihan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ranting_id` (`ranting_id`);

--
-- Indexes for table `kerohanian`
--
ALTER TABLE `kerohanian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `anggota_id` (`anggota_id`),
  ADD KEY `ranting_id` (`ranting_id`),
  ADD KEY `fk_kerohanian_tingkat` (`tingkat_pembuka_id`),
  ADD KEY `idx_tanggal_pembukaan` (`tanggal_pembukaan`),
  ADD KEY `idx_pembuka_nama` (`pembuka_nama`),
  ADD KEY `idx_penyelenggara` (`penyelenggara`);

--
-- Indexes for table `pengurus`
--
ALTER TABLE `pengurus`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pengurus_induk_id` (`pengurus_induk_id`);

--
-- Indexes for table `prestasi`
--
ALTER TABLE `prestasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `anggota_id` (`anggota_id`);

--
-- Indexes for table `ranting`
--
ALTER TABLE `ranting`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sk` (`no_sk_pembentukan`),
  ADD KEY `idx_pengurus_kota_id` (`pengurus_kota_id`),
  ADD KEY `idx_nama_ranting` (`nama_ranting`);

--
-- Indexes for table `ranting_pelatih`
--
ALTER TABLE `ranting_pelatih`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ranting_id` (`ranting_id`),
  ADD KEY `pelatih_id` (`pelatih_id`);

--
-- Indexes for table `tingkatan`
--
ALTER TABLE `tingkatan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `urutan` (`urutan`);

--
-- Indexes for table `ukt`
--
ALTER TABLE `ukt`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tanggal_pelaksanaan` (`tanggal_pelaksanaan`),
  ADD KEY `idx_lokasi` (`lokasi`),
  ADD KEY `idx_penyelenggara_id` (`penyelenggara_id`);

--
-- Indexes for table `ukt_peserta`
--
ALTER TABLE `ukt_peserta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ukt_id` (`ukt_id`),
  ADD KEY `anggota_id` (`anggota_id`),
  ADD KEY `tingkat_dari_id` (`tingkat_dari_id`),
  ADD KEY `tingkat_ke_id` (`tingkat_ke_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_users_pengurus_id` (`pengurus_id`),
  ADD KEY `fk_users_ranting_id` (`ranting_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `anggota`
--
ALTER TABLE `anggota`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `jadwal_latihan`
--
ALTER TABLE `jadwal_latihan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `kerohanian`
--
ALTER TABLE `kerohanian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pengurus`
--
ALTER TABLE `pengurus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=314;

--
-- AUTO_INCREMENT for table `prestasi`
--
ALTER TABLE `prestasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ranting`
--
ALTER TABLE `ranting`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `ranting_pelatih`
--
ALTER TABLE `ranting_pelatih`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tingkatan`
--
ALTER TABLE `tingkatan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `ukt`
--
ALTER TABLE `ukt`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `ukt_peserta`
--
ALTER TABLE `ukt_peserta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `anggota`
--
ALTER TABLE `anggota`
  ADD CONSTRAINT `anggota_ibfk_1` FOREIGN KEY (`ranting_awal_id`) REFERENCES `ranting` (`id`),
  ADD CONSTRAINT `anggota_ibfk_2` FOREIGN KEY (`ranting_saat_ini_id`) REFERENCES `ranting` (`id`),
  ADD CONSTRAINT `anggota_ibfk_3` FOREIGN KEY (`tingkat_id`) REFERENCES `tingkatan` (`id`);

--
-- Constraints for table `jadwal_latihan`
--
ALTER TABLE `jadwal_latihan`
  ADD CONSTRAINT `jadwal_latihan_ibfk_1` FOREIGN KEY (`ranting_id`) REFERENCES `ranting` (`id`);

--
-- Constraints for table `kerohanian`
--
ALTER TABLE `kerohanian`
  ADD CONSTRAINT `fk_kerohanian_tingkat` FOREIGN KEY (`tingkat_pembuka_id`) REFERENCES `tingkatan` (`id`),
  ADD CONSTRAINT `kerohanian_ibfk_1` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`),
  ADD CONSTRAINT `kerohanian_ibfk_2` FOREIGN KEY (`ranting_id`) REFERENCES `ranting` (`id`);

--
-- Constraints for table `pengurus`
--
ALTER TABLE `pengurus`
  ADD CONSTRAINT `pengurus_ibfk_1` FOREIGN KEY (`pengurus_induk_id`) REFERENCES `pengurus` (`id`);

--
-- Constraints for table `prestasi`
--
ALTER TABLE `prestasi`
  ADD CONSTRAINT `prestasi_ibfk_1` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ranting`
--
ALTER TABLE `ranting`
  ADD CONSTRAINT `ranting_ibfk_1` FOREIGN KEY (`pengurus_kota_id`) REFERENCES `pengurus` (`id`);

--
-- Constraints for table `ranting_pelatih`
--
ALTER TABLE `ranting_pelatih`
  ADD CONSTRAINT `ranting_pelatih_ibfk_1` FOREIGN KEY (`ranting_id`) REFERENCES `ranting` (`id`),
  ADD CONSTRAINT `ranting_pelatih_ibfk_2` FOREIGN KEY (`pelatih_id`) REFERENCES `anggota` (`id`);

--
-- Constraints for table `ukt`
--
ALTER TABLE `ukt`
  ADD CONSTRAINT `fk_ukt_penyelenggara` FOREIGN KEY (`penyelenggara_id`) REFERENCES `pengurus` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ukt_peserta`
--
ALTER TABLE `ukt_peserta`
  ADD CONSTRAINT `ukt_peserta_ibfk_1` FOREIGN KEY (`ukt_id`) REFERENCES `ukt` (`id`),
  ADD CONSTRAINT `ukt_peserta_ibfk_2` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`),
  ADD CONSTRAINT `ukt_peserta_ibfk_3` FOREIGN KEY (`tingkat_dari_id`) REFERENCES `tingkatan` (`id`),
  ADD CONSTRAINT `ukt_peserta_ibfk_4` FOREIGN KEY (`tingkat_ke_id`) REFERENCES `tingkatan` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_pengurus` FOREIGN KEY (`pengurus_id`) REFERENCES `pengurus` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_pengurus_id` FOREIGN KEY (`pengurus_id`) REFERENCES `pengurus` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_ranting` FOREIGN KEY (`ranting_id`) REFERENCES `ranting` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_ranting_id` FOREIGN KEY (`ranting_id`) REFERENCES `ranting` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`pengurus_id`) REFERENCES `pengurus` (`id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`ranting_id`) REFERENCES `ranting` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
