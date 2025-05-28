-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 28, 2025 at 06:49 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_penilaian_proyek`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `RecalculateProjectTotals` (IN `p_project_id` INT)   BEGIN
    -- Langkah 1: Update total_mistakes_parameter untuk setiap parameter proyek ini
    -- dengan menjumlahkan kesalahan dari sub_aspects terkait.
    UPDATE project_parameters pp
    SET pp.total_mistakes_parameter = (
        SELECT IFNULL(SUM(sa.mistakes), 0)
        FROM sub_aspects sa
        WHERE sa.parameter_id_fk = pp.parameter_id_pk
    )
    WHERE pp.project_id = p_project_id;

    -- Langkah 2: Update overall_total_mistakes di tabel projects
    -- dengan menjumlahkan total_mistakes_parameter yang baru diupdate.
    UPDATE projects p
    SET p.overall_total_mistakes = (
        SELECT IFNULL(SUM(pp_updated.total_mistakes_parameter), 0)
        FROM project_parameters pp_updated
        WHERE pp_updated.project_id = p_project_id
    )
    WHERE p.project_id = p_project_id;

    -- Kolom overall_total_score akan otomatis diupdate oleh MySQL
    -- karena merupakan generated column (AS (90 - overall_total_mistakes)).
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `project_id` int NOT NULL,
  `project_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `examiner_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `examiner_notes` text COLLATE utf8mb4_unicode_ci,
  `STATUS` enum('LANJUT','ULANG') COLLATE utf8mb4_unicode_ci DEFAULT 'LANJUT',
  `overall_total_mistakes` int DEFAULT '0',
  `overall_total_score` int GENERATED ALWAYS AS ((90 - `overall_total_mistakes`)) STORED,
  `predicate_text` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doc_version` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `user_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`project_id`, `project_name`, `examiner_name`, `examiner_notes`, `STATUS`, `overall_total_mistakes`, `predicate_text`, `doc_version`, `created_at`, `updated_at`, `user_id`) VALUES
(2, 'Tongkat SAKTI', 'Ahmad Zaini', '', 'LANJUT', 20, 'Baik', 'Doc: v28052025-Final', '2025-05-27 17:08:13', '2025-05-27 17:22:22', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `project_parameters`
--

CREATE TABLE `project_parameters` (
  `parameter_id_pk` int NOT NULL,
  `project_id` int NOT NULL,
  `parameter_client_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parameter_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_mistakes_parameter` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `project_parameters`
--

INSERT INTO `project_parameters` (`parameter_id_pk`, `project_id`, `parameter_client_id`, `parameter_name`, `total_mistakes_parameter`) VALUES
(14, 2, 'param_1748365564307', 'Kerja sama tim', 0),
(15, 2, 'param_1748365566802', 'Kesesuaian', 0);

-- --------------------------------------------------------

--
-- Table structure for table `sub_aspects`
--

CREATE TABLE `sub_aspects` (
  `sub_aspect_id_pk` int NOT NULL,
  `parameter_id_fk` int NOT NULL,
  `sub_aspect_client_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_aspect_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mistakes` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sub_aspects`
--

INSERT INTO `sub_aspects` (`sub_aspect_id_pk`, `parameter_id_fk`, `sub_aspect_client_id`, `sub_aspect_name`, `mistakes`) VALUES
(44, 14, 'sub_1748365564307', 'Kekompakan', 1),
(45, 14, 'sub_1748365581623_bdeg1', 'penyelesaian masalah', 3),
(46, 14, 'sub_1748365581791_1te2b', 'Komunikasi', 3),
(47, 15, 'sub_1748365566802', 'hardware', 2),
(48, 15, 'sub_1748365618780_4dudr', 'software', 2),
(49, 15, 'sub_1748365618943_fm1ku', 'Machine learning', 4),
(50, 15, 'sub_1748365619163_rvpho', 'integrasi', 5);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`project_id`);

--
-- Indexes for table `project_parameters`
--
ALTER TABLE `project_parameters`
  ADD PRIMARY KEY (`parameter_id_pk`),
  ADD KEY `idx_project_id_params` (`project_id`);

--
-- Indexes for table `sub_aspects`
--
ALTER TABLE `sub_aspects`
  ADD PRIMARY KEY (`sub_aspect_id_pk`),
  ADD KEY `idx_param_id_sub_aspects` (`parameter_id_fk`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `project_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `project_parameters`
--
ALTER TABLE `project_parameters`
  MODIFY `parameter_id_pk` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `sub_aspects`
--
ALTER TABLE `sub_aspects`
  MODIFY `sub_aspect_id_pk` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `project_parameters`
--
ALTER TABLE `project_parameters`
  ADD CONSTRAINT `project_parameters_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE;

--
-- Constraints for table `sub_aspects`
--
ALTER TABLE `sub_aspects`
  ADD CONSTRAINT `sub_aspects_ibfk_1` FOREIGN KEY (`parameter_id_fk`) REFERENCES `project_parameters` (`parameter_id_pk`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
