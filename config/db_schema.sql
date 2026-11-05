-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 05, 2026 at 11:34 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `amsc_indaw`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `ID` int(11) NOT NULL,
  `YearCode` varchar(20) NOT NULL,
  `Semester` varchar(20) NOT NULL,
  `IsActive` tinyint(1) DEFAULT 0,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `EnrollmentStatus` enum('Open','Closed') DEFAULT 'Closed',
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`ID`, `YearCode`, `Semester`, `IsActive`, `CreatedAt`, `EnrollmentStatus`, `UpdatedAt`) VALUES
(2, '2026-2027', '2nd Semester', 1, '2026-11-01 08:50:22', 'Open', '2026-11-04 10:54:42');

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `ID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `Title` varchar(50) NOT NULL,
  `Description` varchar(155) DEFAULT NULL,
  `LoggedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`ID`, `UserID`, `Title`, `Description`, `LoggedAt`) VALUES
(628, 1, 'Prospectus Subject Removed', 'Removed mapping ID 7 from curriculum #7.', '2026-11-02 03:03:10'),
(629, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 8 in curriculum #7.', '2026-11-02 03:28:03'),
(630, 1, 'Prospectus Subject Added', 'Added mapping to curriculum #7.', '2026-11-02 03:28:28'),
(631, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 17 in curriculum #7.', '2026-11-02 03:29:01'),
(632, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 19 in curriculum #7.', '2026-11-02 03:34:14'),
(633, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 29 in curriculum #7.', '2026-11-02 03:34:25'),
(634, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 39 in curriculum #7.', '2026-11-02 03:34:38'),
(635, 1, 'Subject Deleted', 'Deleted subject: Tech Elect.', '2026-11-02 03:35:37'),
(636, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 32 in curriculum #7.', '2026-11-02 03:41:09'),
(637, 1, 'Prospectus Subject Added', 'Added mapping to curriculum #7.', '2026-11-02 03:42:06'),
(638, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 45 in curriculum #7.', '2026-11-02 03:42:45'),
(639, 1, 'Prospectus Subject Added', 'Added mapping to curriculum #7.', '2026-11-02 03:43:47'),
(640, 1, 'Prospectus Subject Added', 'Added mapping to curriculum #7.', '2026-11-02 03:44:13'),
(641, 1, 'Prospectus Subject Added', 'Added mapping to curriculum #7.', '2026-11-02 03:44:53'),
(642, 1, 'Prospectus Subject Added', 'Added mapping to curriculum #7.', '2026-11-02 03:45:26'),
(643, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 70 in curriculum #7.', '2026-11-02 03:45:45'),
(644, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 24 in curriculum #7.', '2026-11-02 03:46:03'),
(645, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 71 in curriculum #7.', '2026-11-02 03:46:15'),
(646, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 24 in curriculum #7.', '2026-11-02 03:46:24'),
(647, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 70 in curriculum #7.', '2026-11-02 03:47:04'),
(648, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 67 in curriculum #7.', '2026-11-02 03:47:10'),
(649, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 69 in curriculum #7.', '2026-11-02 03:47:22'),
(650, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 68 in curriculum #7.', '2026-11-02 03:47:32'),
(651, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 32 in curriculum #7.', '2026-11-02 03:47:45'),
(652, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 71 in curriculum #7.', '2026-11-02 03:47:55'),
(653, 1, 'Prospectus Subject Added', 'Added mapping to curriculum #7.', '2026-11-02 03:48:24'),
(654, 1, 'Prospectus Subject Added', 'Added mapping to curriculum #7.', '2026-11-02 03:49:12'),
(655, 1, 'Prospectus Subject Added', 'Added mapping to curriculum #7.', '2026-11-02 03:49:29'),
(656, 1, 'Prospectus Subject Added', 'Added mapping to curriculum #7.', '2026-11-02 03:49:42'),
(657, 1, 'Prospectus Subject Added', 'Added mapping to curriculum #7.', '2026-11-02 03:49:56'),
(658, 1, 'Prospectus Subject Added', 'Added mapping to curriculum #7.', '2026-11-02 03:50:08'),
(659, 1, 'Prospectus Subject Added', 'Added mapping to curriculum #7.', '2026-11-02 03:50:19'),
(660, 1, 'Prospectus Subject Added', 'Added mapping to curriculum #7.', '2026-11-02 03:50:31'),
(661, 1, 'Prospectus Subject Added', 'Added mapping to curriculum #7.', '2026-11-02 03:50:49'),
(662, 1, 'Prospectus Subject Added', 'Added mapping to curriculum #7.', '2026-11-02 03:51:04'),
(663, 1, 'Prospectus Subject Added', 'Added mapping to curriculum #7.', '2026-11-02 03:51:27'),
(664, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 19 in curriculum #7.', '2026-11-02 03:57:55'),
(665, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 19 in curriculum #7.', '2026-11-02 04:02:15'),
(666, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 19 in curriculum #7.', '2026-11-02 04:04:46'),
(667, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 19 in curriculum #7.', '2026-11-02 04:09:37'),
(668, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 17 in curriculum #7.', '2026-11-02 04:10:21'),
(669, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 18 in curriculum #7.', '2026-11-02 04:10:33'),
(670, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 18 in curriculum #7.', '2026-11-02 04:10:44'),
(671, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 21 in curriculum #7.', '2026-11-02 04:11:27'),
(672, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 18 in curriculum #7.', '2026-11-02 04:11:39'),
(673, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 18 in curriculum #7.', '2026-11-02 04:11:47'),
(674, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 38 in curriculum #7.', '2026-11-02 04:18:34'),
(675, 1, 'Subject Updated', 'Updated subject: ARABIC103.', '2026-11-02 04:37:17'),
(676, 1, 'Subject Updated', 'Updated subject: ARABIC103.', '2026-11-02 04:40:06'),
(677, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 28 in curriculum #7.', '2026-11-02 04:54:39'),
(678, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 26 in curriculum #7.', '2026-11-02 04:55:31'),
(679, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 25 in curriculum #7.', '2026-11-02 04:56:05'),
(680, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 29 in curriculum #7.', '2026-11-02 04:56:34'),
(681, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 34 in curriculum #7.', '2026-11-02 04:57:14'),
(682, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 35 in curriculum #7.', '2026-11-02 04:57:30'),
(683, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 37 in curriculum #7.', '2026-11-02 04:57:49'),
(684, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 33 in curriculum #7.', '2026-11-02 04:58:04'),
(685, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 39 in curriculum #7.', '2026-11-02 04:58:20'),
(686, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 32 in curriculum #7.', '2026-11-02 04:58:49'),
(687, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 18 in curriculum #7.', '2026-11-02 05:04:29'),
(688, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 21 in curriculum #7.', '2026-11-02 05:04:47'),
(689, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 19 in curriculum #7.', '2026-11-02 05:05:28'),
(690, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 18 in curriculum #7.', '2026-11-02 05:08:25'),
(691, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 17 in curriculum #7.', '2026-11-02 05:08:47'),
(692, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 32 in curriculum #7.', '2026-11-02 05:09:18'),
(693, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 32 in curriculum #7.', '2026-11-02 05:10:35'),
(694, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 29 in curriculum #7.', '2026-11-02 05:12:05'),
(695, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 39 in curriculum #7.', '2026-11-02 05:12:38'),
(696, 1, 'Curriculum Updated', 'Updated curriculum ID 7: Revised BSIT Curriculum CMO No. 25, Series of 2015.', '2026-11-02 05:13:09'),
(697, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 62 in curriculum #7.', '2026-11-02 05:22:02'),
(698, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 32 in curriculum #7.', '2026-11-02 05:24:07'),
(699, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 32 in curriculum #7.', '2026-11-02 05:25:55'),
(700, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 21 in curriculum #7.', '2026-11-02 10:00:07'),
(701, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 19 in curriculum #7.', '2026-11-02 10:00:27'),
(702, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 28 in curriculum #7.', '2026-11-02 10:00:48'),
(703, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 26 in curriculum #7.', '2026-11-02 10:01:31'),
(704, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 25 in curriculum #7.', '2026-11-02 10:01:55'),
(705, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 38 in curriculum #7.', '2026-11-02 10:02:12'),
(706, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 34 in curriculum #7.', '2026-11-02 10:02:39'),
(707, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 35 in curriculum #7.', '2026-11-02 10:02:59'),
(708, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 37 in curriculum #7.', '2026-11-02 10:03:16'),
(709, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 33 in curriculum #7.', '2026-11-02 10:03:45'),
(710, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 43 in curriculum #7.', '2026-11-02 10:04:15'),
(711, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 42 in curriculum #7.', '2026-11-02 10:04:41'),
(712, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 44 in curriculum #7.', '2026-11-02 10:04:56'),
(713, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 45 in curriculum #7.', '2026-11-02 10:05:31'),
(714, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 67 in curriculum #7.', '2026-11-02 10:05:54'),
(715, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 50 in curriculum #7.', '2026-11-02 10:06:16'),
(716, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 51 in curriculum #7.', '2026-11-02 10:06:33'),
(717, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 52 in curriculum #7.', '2026-11-02 10:07:09'),
(718, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 53 in curriculum #7.', '2026-11-02 10:08:01'),
(719, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 68 in curriculum #7.', '2026-11-02 10:08:38'),
(720, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 69 in curriculum #7.', '2026-11-02 10:08:54'),
(721, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 57 in curriculum #7.', '2026-11-02 10:09:22'),
(722, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 58 in curriculum #7.', '2026-11-02 10:09:45'),
(723, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 59 in curriculum #7.', '2026-11-02 10:10:09'),
(724, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 70 in curriculum #7.', '2026-11-02 10:10:29'),
(725, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 71 in curriculum #7.', '2026-11-02 10:10:51'),
(726, 1, 'Prospectus Subject Updated', 'Updated subject mapping ID 62 in curriculum #7.', '2026-11-02 10:43:20'),
(727, 1, 'Logs in Back Automatically', 'User session is still active.', '2026-11-02 23:25:54'),
(728, 1, 'Logs in Back Automatically', 'User session is still active.', '2026-11-02 23:27:29'),
(729, 4, 'New Applicant Account Created.', 'Applicant @dkboygrar\'s has successfully been created. (ID: 4).', '2026-11-02 23:28:39'),
(730, 4, 'Login attempt successful', 'Successfully logs in.', '2026-11-02 23:29:10'),
(731, 4, 'Logout', 'Logs out successfully. User session destroyed.', '2026-11-02 23:32:08'),
(732, 4, 'Login attempt failed', 'Enters incorrect password.', '2026-11-02 23:35:59'),
(733, 4, 'Login attempt successful', 'Successfully logs in.', '2026-11-02 23:36:06'),
(734, 4, 'Profile Update Failed', 'Incomplete information. Please fill in all required fields.', '2026-11-03 01:03:38'),
(735, 4, 'Profile Update Failed', 'Incomplete information. Please fill in all required fields.', '2026-11-03 01:03:58'),
(736, 4, 'Profile Created', 'New applicant profile created successfully.', '2026-11-03 01:05:39'),
(737, 4, 'Profile Update Failed', 'Incomplete information. Please fill in all required fields.', '2026-11-03 01:35:09'),
(738, 4, 'Profile Update Failed', 'Unknown application number.', '2026-11-03 01:35:29'),
(739, 1, 'Logout', 'Logs out successfully. User session destroyed.', '2026-11-03 01:43:52'),
(740, 4, 'Applicant Profile Updated', 'Applicant profile updated successfully.', '2026-11-03 01:52:52'),
(741, 4, 'Applicant Profile Updated', 'Applicant profile updated successfully.', '2026-11-03 01:53:35'),
(742, 1, 'Login attempt successful', 'Successfully logs in.', '2026-11-03 01:54:49'),
(743, 1, 'Logout', 'Logs out successfully. User session destroyed.', '2026-11-03 05:30:19'),
(744, 4, 'Login attempt failed', 'Enters incorrect password.', '2026-11-03 05:30:29'),
(745, 1, 'Login attempt successful', 'Successfully logs in.', '2026-11-03 05:43:23'),
(746, 1, 'Employee Record Created', 'Added new employee personnel: Amer Hussien Cabuntalan (AMSC-EMP-20260070).', '2026-11-03 05:44:17'),
(747, 1, 'Employee Record Updated', 'Updated profile details for Alinor Abdulazis (ID: AMSC-EMP-20260006).', '2026-11-03 07:44:53'),
(748, 1, 'Logs in Back Automatically', 'User session is still active.', '2026-11-03 08:02:25'),
(749, 1, 'Logs in Back Automatically', 'User session is still active.', '2026-11-03 14:39:52'),
(750, 1, 'Login attempt successful', 'Successfully logs in.', '2026-11-04 07:39:12'),
(751, 1, 'Login attempt successful', 'Successfully logs in.', '2026-11-04 08:39:24'),
(752, 1, 'Logs in Back Automatically', 'User session is still active.', '2026-11-04 09:20:05'),
(753, 1, 'Logout', 'Logs out successfully. User session destroyed.', '2026-11-04 09:20:55'),
(754, 4, 'Login attempt successful', 'Successfully logs in.', '2026-11-04 09:23:03'),
(755, 4, 'Applicant Profile Updated', 'Applicant profile updated successfully.', '2026-11-04 09:24:51'),
(756, 4, 'Logs in Back Automatically', 'User session is still active.', '2026-11-04 09:25:46'),
(757, 4, 'Applicant Profile Updated', 'Applicant profile updated successfully.', '2026-11-04 09:26:12'),
(758, 4, 'Applicant Profile Updated', 'Applicant profile updated successfully.', '2026-11-04 09:26:23'),
(759, 4, 'Logout', 'Logs out successfully. User session destroyed.', '2026-11-04 09:26:40'),
(760, 4, 'Login attempt successful', 'Successfully logs in.', '2026-11-04 09:27:25'),
(761, 4, 'Logout', 'Logs out successfully. User session destroyed.', '2026-11-04 09:27:43'),
(762, 4, 'Login attempt successful', 'Successfully logs in.', '2026-11-04 09:28:15'),
(763, 4, 'Logout', 'Logs out successfully. User session destroyed.', '2026-11-04 09:28:20'),
(764, 1, 'Login attempt successful', 'Successfully logs in.', '2026-11-04 09:30:03'),
(765, 1, 'Academic Term Updated', 'Changed active academic term to: 1st Semester, AY 2026-2027', '2026-11-04 09:40:47'),
(766, 1, 'Academic Term Updated', 'Changed active academic term to: 1st Semester, AY 2026-2027', '2026-11-04 09:41:11'),
(767, 1, 'Academic Term Updated', 'Changed active academic term to: 1st Semester, AY 2026-2027', '2026-11-04 09:41:19'),
(768, 1, 'Academic Term Updated', 'Changed active academic term to: 1st Semester, AY 2026-2027', '2026-11-04 09:41:49'),
(769, 1, 'Academic Term Updated', 'Changed active academic term to: 1st Semester, AY 2026-2027', '2026-11-04 09:42:10'),
(770, 1, 'Academic Term Updated', 'Changed active academic term to: 1st Semester, AY 2026-2027', '2026-11-04 09:42:13'),
(771, 1, 'Academic Term Updated', 'Changed active academic term to: 1st Semester, AY 2026-2027', '2026-11-04 09:42:19'),
(772, 1, 'Academic Term Updated', 'Changed active academic term to: 1st Semester, AY 2026-2027', '2026-11-04 09:43:26'),
(773, 1, 'Academic Term Viewed', 'Switched view to academic year ID: 3', '2026-11-04 10:01:33'),
(774, 1, 'Academic Term Viewed', 'Switched view to academic year ID: 2', '2026-11-04 10:01:44'),
(775, 1, 'Academic Term Updated', 'Updated term: 2026-2027 2nd Semester.', '2026-11-04 10:54:24'),
(776, 1, 'Academic Term Updated', 'Updated term: 2026-2027 2nd Semester.', '2026-11-04 10:54:42'),
(777, 1, 'Logout', 'Logs out successfully. User session destroyed.', '2026-11-04 11:07:38'),
(778, 4, 'Login attempt successful', 'Successfully logs in.', '2026-11-04 11:07:46'),
(779, 4, 'Login attempt successful', 'Successfully logs in.', '2026-11-04 11:47:52'),
(780, 4, 'Logout', 'Logs out successfully. User session destroyed.', '2026-11-04 13:45:48'),
(781, 1, 'Login attempt successful', 'Successfully logs in.', '2026-11-04 13:46:03'),
(782, 1, 'Designation Deleted', 'Deleted designation ID 2.', '2026-11-04 13:49:22'),
(783, 1, 'Designation Added', 'Added new designation for staff #2.', '2026-11-04 13:49:32'),
(784, 1, 'Class Created', 'Created new class: GEC101 (WwWw).', '2026-11-04 19:01:43'),
(785, 1, 'Class Created', 'Created new class: ARABIC103 - YyAa.', '2026-11-04 19:07:35'),
(786, 1, 'Class Opened', 'Enrollment for class ID #2 is closed.', '2026-11-04 19:23:36'),
(787, 1, 'Class opened', 'Enrollment for class ID #1 is opened.', '2026-11-04 19:24:13'),
(788, 1, 'Class Petition Status Changed', 'Class ID #2 is made a petition class.', '2026-11-04 19:34:53'),
(789, 1, 'Class Information Updated', 'Updated details for class ID #2 (ARABIC103 - YyAa).', '2026-11-04 19:38:14'),
(790, 1, 'Class Information Updated', 'Updated details for class ID #2 (ARABIC103 - YyAa).', '2026-11-04 19:39:59'),
(791, 1, 'Class Schedule Updated', 'Updated schedule #2 for class #2.', '2026-11-04 19:40:12'),
(792, 1, 'Class Schedule Added', 'Added schedule session to class #2.', '2026-11-04 19:40:50'),
(793, 1, 'Class Schedule Updated', 'Updated schedule #3 for class #2.', '2026-11-04 19:42:11'),
(794, 1, 'Logout', 'Logs out successfully. User session destroyed.', '2026-11-05 01:05:20'),
(795, 4, 'Login attempt successful', 'Successfully logs in.', '2026-11-05 01:05:30'),
(796, 4, 'Applicant Profile Updated', 'Applicant profile updated successfully.', '2026-11-05 02:01:38'),
(797, 4, 'Account Settings Updated', 'User successfully updated account credentials.', '2026-11-05 02:02:26'),
(798, 4, 'Account Update Failed', 'Username or Email is already taken by another user.', '2026-11-05 02:02:40'),
(799, 4, 'Account Settings Updated', 'User successfully updated account credentials.', '2026-11-05 02:02:48'),
(800, 4, 'Account Update Failed', 'Username or Email is already taken by another user.', '2026-11-05 02:02:55'),
(801, 4, 'Account Update Failed', 'Username or Email is already taken by another user.', '2026-11-05 02:04:01'),
(802, 4, 'Account Settings Updated', 'User successfully updated account credentials.', '2026-11-05 02:04:07'),
(803, 4, 'Applicant Profile Updated', 'Applicant profile updated successfully.', '2026-11-05 02:04:45'),
(804, 4, 'Document Uploaded', 'Uploaded requirement: Birth Certificate (either PSA or Local Civil Registrar) (Grar_AhmadKiram_BirthCertificateeitherPSAorLocalCivilRegistrar_05112026.pdf)', '2026-11-05 02:31:28'),
(805, 4, 'Logs in Back Automatically', 'User session is still active.', '2026-11-05 02:32:54'),
(806, 4, 'Document Deleted', 'Deleted requirement: Birth Certificate (either PSA or Local Civil Registrar).', '2026-11-05 02:34:04'),
(807, 4, 'Logout', 'Logs out successfully. User session destroyed.', '2026-11-05 02:35:53'),
(808, 1, 'Login attempt successful', 'Successfully logs in.', '2026-11-05 02:37:02'),
(809, 4, 'Document Uploaded', 'Uploaded requirement: Birth Certificate (Grar_AhmadKiram_BirthCertificate_05112026.pdf).', '2026-11-05 02:37:44'),
(810, 4, 'Document Uploaded', 'Uploaded requirement: Diploma (Grar_AhmadKiram_Diploma_05112026.jpeg).', '2026-11-05 02:38:07'),
(811, 4, 'Application Submitted', 'Applicant submitted their application for review.', '2026-11-05 02:38:45'),
(812, 1, 'Document Verified', 'Updated document ID #3 to Approved.', '2026-11-05 02:39:33'),
(813, 1, 'Document Verified', 'Updated document ID #2 to Rejected.', '2026-11-05 02:42:02'),
(814, 1, 'Application Returned', 'Returned applicant ID #1 for profile revision and/or document re-submission.', '2026-11-05 02:42:43'),
(815, 4, 'Application Submitted', 'Applicant submitted their application for review.', '2026-11-05 02:57:39'),
(816, 1, 'Document Verified', 'Updated document ID #2 to Approved.', '2026-11-05 03:07:37'),
(817, 1, 'Document Verified', 'Updated document ID #2 to Rejected.', '2026-11-05 03:08:08'),
(818, 1, 'Application Rejected', 'Rejected applicant ID #1.', '2026-11-05 03:08:21'),
(819, 1, 'Application Status Undone', 'Reverted applicant ID #1 status to Under Review.', '2026-11-05 03:08:59'),
(820, 1, 'Application Approved', 'Approved applicant ID #1 and generated Student ID.', '2026-11-05 03:09:16'),
(821, 1, 'Approval Undone', 'Reverted approved applicant ID #1 back to Under Review and removed student record.', '2026-11-05 03:17:34'),
(822, 1, 'Application Returned', 'Returned applicant ID #1 for profile revision and/or document re-submission.', '2026-11-05 03:17:47'),
(823, 4, 'Applicant Profile Updated', 'Applicant profile details updated successfully.', '2026-11-05 03:56:04'),
(824, 4, 'Applicant Profile Updated', 'Applicant profile details updated successfully.', '2026-11-05 03:56:35'),
(825, 4, 'Verification Email Sent', 'Verification link dispatched to email.', '2026-11-05 04:03:43'),
(826, 4, 'Verification Email Sent', 'Verification link dispatched to email.', '2026-11-05 04:09:59'),
(827, 4, 'Verification Email Sent', 'Verification link dispatched to email.', '2026-11-05 04:15:04'),
(828, 4, 'Verification Email Sent', 'Verification link dispatched to email.', '2026-11-05 04:15:34'),
(829, 4, 'Verification Email Sent', 'Verification link dispatched to email.', '2026-11-05 04:16:09'),
(830, 4, 'Verification Email Sent', 'Verification link dispatched to email.', '2026-11-05 04:16:20'),
(831, 4, 'Email Verified', 'User successfully verified email address via link.', '2026-11-05 04:24:54'),
(832, 4, 'Application Submitted', 'Applicant submitted their application for review.', '2026-11-05 04:43:22'),
(833, 1, 'Application Approved', 'Approved applicant ID #1 and generated Student ID.', '2026-11-05 04:43:37'),
(834, 1, 'Approval Undone', 'Reverted approved applicant ID #1 back to Under Review and removed student record.', '2026-11-05 04:48:11'),
(835, 1, 'Application Approved', 'Approved applicant ID #1 and generated Student ID.', '2026-11-05 04:48:17'),
(836, 1, 'Class Schedule Updated', 'Updated schedule #1 for class #1.', '2026-11-05 04:49:10'),
(837, 1, 'Class Information Updated', 'Updated details for class ID #1 (GEC101 (WwWw)).', '2026-11-05 04:51:22'),
(838, 1, 'Class opened', 'Enrollment for class ID #2 is opened.', '2026-11-05 04:51:40'),
(839, 1, 'Class Petition Status Changed', 'Class ID #1 is made a petition class.', '2026-11-05 04:52:14'),
(840, 1, 'Class Created', 'Created new class: PE 2 Pickleball.', '2026-11-05 04:53:26'),
(841, 1, 'Class Schedule Updated', 'Updated schedule #4 for class #3.', '2026-11-05 04:57:01'),
(842, 1, 'Class Schedule Updated', 'Updated schedule #4 for class #3.', '2026-11-05 04:57:40'),
(843, 1, 'Class locked', 'Enrollment for class ID #3 is locked.', '2026-11-05 04:58:29'),
(844, 1, 'Class opened', 'Enrollment for class ID #3 is opened.', '2026-11-05 04:58:40'),
(845, 1, 'Class Created', 'Created new class: ARABIC 101.', '2026-11-05 05:13:53'),
(846, 1, 'Class opened', 'Enrollment for class ID #4 is opened.', '2026-11-05 05:13:59'),
(847, 1, 'Class Petition Status Changed', 'Class ID #4 is made a petition class.', '2026-11-05 05:14:10'),
(848, 1, 'Class Updated', 'Updated class record: PE 2 Pickleball.', '2026-11-05 05:15:05'),
(849, 1, 'Class Schedule Updated', 'Updated schedule #4 for class #3.', '2026-11-05 05:15:35'),
(850, 1, 'Class Schedule Added', 'Added schedule session to class #3.', '2026-11-05 05:16:12'),
(851, 1, 'Class Petition Status Changed', 'Class ID #3 is made a petition class.', '2026-11-05 05:16:23'),
(852, 1, 'Class Updated', 'Updated class record: ARABIC103 - YyAa.', '2026-11-05 05:16:36'),
(853, 4, 'Enrollment Draft Saved', 'Student successfully updated enrollment status to Pre-Enrolled.', '2026-11-05 05:23:04'),
(854, 1, 'Student Status Updated', 'Updated student enrollment status (remove) for enrollment #1.', '2026-11-05 05:42:35'),
(855, 4, 'Enrollment Draft Saved', 'Student successfully updated enrollment status to Pre-Enrolled.', '2026-11-05 05:43:43'),
(856, 4, 'Enrollment Confirmed', 'Student successfully updated enrollment status to Enrolled.', '2026-11-05 05:44:06'),
(857, 1, 'Class Created', 'Created new class: ARABIC103 - Bb.', '2026-11-05 06:23:22'),
(858, 1, 'Class Updated', 'Updated class record: ARABIC103 - Bb.', '2026-11-05 06:23:34'),
(859, 1, 'Class Updated', 'Updated class record: ARABIC103 - Bb.', '2026-11-05 06:32:44'),
(860, 1, 'Class Updated', 'Updated class record: ARABIC103 - YyAa.', '2026-11-05 06:32:53'),
(861, 1, 'Student Reassigned', 'Reassigned student #3 from class #2 to class #5.', '2026-11-05 06:34:04'),
(862, 1, 'Student Status Updated', 'Updated student enrollment status (drop) for enrollment #5.', '2026-11-05 06:34:25'),
(863, 1, 'Student Re-enrolled', 'Re-enrolled student from enrollment #5 back into class #5.', '2026-11-05 06:37:01'),
(864, 1, 'Class Schedule Added', 'Added schedule session to class #5.', '2026-11-05 06:38:08'),
(865, 1, 'Student Reassigned', 'Reassigned student #3 from class #5 to class #2.', '2026-11-05 06:50:02'),
(866, 1, 'Approval Undone', 'Reverted approved applicant ID #1 back to Under Review and removed student record.', '2026-11-05 06:50:41'),
(867, 1, 'Application Approved', 'Approved applicant ID #1 and generated Student ID.', '2026-11-05 06:50:49'),
(868, 1, 'Login attempt successful', 'Successfully logs in.', '2026-11-05 07:22:11'),
(869, 1, 'Class Updated', 'Updated class record: ARABIC103 - Bb.', '2026-11-05 07:22:33'),
(870, 1, 'Class Updated', 'Updated class record: GEC101-A.', '2026-11-05 07:22:46'),
(871, 1, 'Class Updated', 'Updated class record: GEC101A.', '2026-11-05 07:22:53'),
(872, 1, 'Class Updated', 'Updated class record: GEC101-A.', '2026-11-05 07:23:00'),
(873, 1, 'Class Updated', 'Updated class record: GEC101-B.', '2026-11-05 07:23:10'),
(874, 4, 'Login attempt successful', 'Successfully logs in.', '2026-11-05 07:29:34'),
(875, 4, 'Enrollment Draft Saved', 'Student successfully updated enrollment status to Pre-Enrolled.', '2026-11-05 07:30:55'),
(876, 4, 'Enrollment Draft Saved', 'Student successfully updated enrollment status to Pre-Enrolled.', '2026-11-05 07:36:34'),
(877, 4, 'Enrollment Confirmed', 'Student successfully updated enrollment status to Enrolled.', '2026-11-05 07:37:37'),
(878, 1, 'Shift Student Program', 'Shifted student ID #4 to Program ID #5.', '2026-11-05 08:50:44'),
(879, 1, 'Shift Student Program', 'Shifted student ID #4 to Program ID #9.', '2026-11-05 09:01:50'),
(880, 1, 'Email Verified', 'User successfully verified email address via link.', '2026-11-05 09:09:34'),
(881, 1, 'Section Pool Created', 'Created new section pool: BSIT Barathium', '2026-11-05 09:17:07'),
(882, 1, 'Student Added to Section', 'Added student ID 4 to section ID 1.', '2026-11-05 09:17:17'),
(883, 1, 'Section Removed', 'Removed section ID 1.', '2026-11-05 09:24:08'),
(884, 1, 'Section Pool Created', 'Created new section pool: BSIT Barakos', '2026-11-05 09:24:26'),
(885, 1, 'Student Added to Section', 'Added student ID 4 to section ID 2.', '2026-11-05 09:24:37'),
(886, 1, 'Student Removed from Section', 'Removed student mapping ID 2 from section.', '2026-11-05 09:26:48'),
(887, 1, 'Student Added to Section', 'Added student ID 4 to section ID 2.', '2026-11-05 09:26:53'),
(888, 1, 'Section Removed', 'Removed section ID 2.', '2026-11-05 09:39:06'),
(889, 1, 'Section Pool Created', 'Created new section pool: BSIT Green Goblins', '2026-11-05 09:39:21'),
(890, 1, 'Student Added to Section', 'Added student ID 4 to section ID 3 via AJAX.', '2026-11-05 09:44:55'),
(891, 1, 'Student Removed from Section', 'Removed student mapping ID 4 from section via AJAX.', '2026-11-05 09:44:58'),
(892, 1, 'Academic Term Deleted', 'Deleted academic term ID 3.', '2026-11-05 09:51:44');

-- --------------------------------------------------------

--
-- Table structure for table `applicants`
--

CREATE TABLE `applicants` (
  `ApplicantID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `ApplicationNumber` varchar(30) NOT NULL,
  `FirstName` varchar(50) NOT NULL,
  `MiddleName` varchar(50) DEFAULT NULL,
  `LastName` varchar(50) NOT NULL,
  `ExtName` enum('Jr.','Sr.','II','III','IV','V','VI') DEFAULT NULL,
  `Gender` enum('Male','Female') NOT NULL,
  `BirthDate` date NOT NULL,
  `ContactNumber` varchar(20) NOT NULL,
  `Address` text NOT NULL,
  `PreferredProgramID_1` int(11) NOT NULL,
  `PreferredProgramID_2` int(11) DEFAULT NULL,
  `PreferredProgramID_3` int(11) DEFAULT NULL,
  `ApplicationStatus` enum('Pending','Under Review','Approved','Rejected') DEFAULT 'Pending',
  `SubmittedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `ProfilePicture` varchar(255) DEFAULT NULL,
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `applicants`
--

INSERT INTO `applicants` (`ApplicantID`, `UserID`, `ApplicationNumber`, `FirstName`, `MiddleName`, `LastName`, `ExtName`, `Gender`, `BirthDate`, `ContactNumber`, `Address`, `PreferredProgramID_1`, `PreferredProgramID_2`, `PreferredProgramID_3`, `ApplicationStatus`, `SubmittedAt`, `ProfilePicture`, `UpdatedAt`) VALUES
(1, 4, 'APP-20260004', 'Ahmad Kiram', 'Alonto', 'Grar', '', 'Male', '1994-12-04', '0909 435 3904', 'Brgy. Moncado Kadingilan, Marawi City', 9, 2, 4, 'Approved', '2026-11-03 01:05:39', 'AHMAD_KIRAM_GRAR-student-20261105045635.jpeg', '2026-11-05 06:50:49');

-- --------------------------------------------------------

--
-- Table structure for table `applicant_documents`
--

CREATE TABLE `applicant_documents` (
  `DocumentID` int(11) NOT NULL,
  `ApplicantID` int(11) NOT NULL,
  `DocumentType` varchar(100) NOT NULL,
  `FilePath` varchar(255) NOT NULL,
  `VerificationStatus` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `Remarks` varchar(255) DEFAULT NULL,
  `UploadedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `VerifiedBy` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `applicant_documents`
--

INSERT INTO `applicant_documents` (`DocumentID`, `ApplicantID`, `DocumentType`, `FilePath`, `VerificationStatus`, `Remarks`, `UploadedAt`, `UpdatedAt`, `VerifiedBy`) VALUES
(2, 1, 'Birth Certificate', 'Grar_AhmadKiram_BirthCertificate_05112026.pdf', 'Rejected', 'Unclear PSA', '2026-11-05 02:37:44', '2026-11-05 03:08:07', NULL),
(3, 1, 'Diploma', 'Grar_AhmadKiram_Diploma_05112026.jpeg', 'Approved', '', '2026-11-05 02:38:07', '2026-11-05 02:39:33', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `block_section_students`
--

CREATE TABLE `block_section_students` (
  `ID` int(11) NOT NULL,
  `SectionID` int(11) NOT NULL,
  `StudentID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `ID` int(11) NOT NULL,
  `ClassName` varchar(50) NOT NULL,
  `CurriculumSubjectID` int(11) NOT NULL,
  `InstructorID` int(11) DEFAULT NULL,
  `AcademicYearID` int(11) NOT NULL,
  `MaxCapacity` int(11) DEFAULT 40,
  `IsEnrollmentOpen` tinyint(1) DEFAULT 0,
  `IsPetition` tinyint(1) DEFAULT 0,
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`ID`, `ClassName`, `CurriculumSubjectID`, `InstructorID`, `AcademicYearID`, `MaxCapacity`, `IsEnrollmentOpen`, `IsPetition`, `UpdatedAt`) VALUES
(1, 'GEC101 (WwWw)', 1, 65, 2, 40, 1, 1, '2026-11-05 04:52:14'),
(2, 'GEC101-A', 1, 2, 2, 40, 1, 1, '2026-11-05 07:22:46'),
(3, 'PE 2 Pickleball', 8, 18, 2, 40, 1, 1, '2026-11-05 05:16:23'),
(4, 'ARABIC 101', 66, 11, 2, 40, 1, 1, '2026-11-05 05:14:10'),
(5, 'GEC101-B', 1, 2, 2, 40, 1, 1, '2026-11-05 07:23:10');

-- --------------------------------------------------------

--
-- Table structure for table `class_schedules`
--

CREATE TABLE `class_schedules` (
  `ID` int(11) NOT NULL,
  `ClassID` int(11) NOT NULL,
  `Description` varchar(50) NOT NULL,
  `DayOfWeek` varchar(20) NOT NULL,
  `StartTime` time NOT NULL,
  `EndTime` time NOT NULL,
  `RoomAssignment` varchar(50) DEFAULT 'TBA',
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `class_schedules`
--

INSERT INTO `class_schedules` (`ID`, `ClassID`, `Description`, `DayOfWeek`, `StartTime`, `EndTime`, `RoomAssignment`, `UpdatedAt`) VALUES
(1, 1, 'Lecture', 'WF', '08:00:00', '10:30:00', 'TBA', '2026-11-05 04:49:10'),
(2, 2, 'Lecture', 'TTh', '16:00:00', '17:30:00', 'Room 101', '2026-11-04 19:40:12'),
(3, 2, 'Lecture', 'FS', '10:00:00', '11:30:00', 'TBA', '2026-11-04 19:42:11'),
(4, 3, 'Lecture', 'W', '10:00:00', '11:00:00', 'TBA', '2026-11-05 05:15:35'),
(5, 4, 'Lecture', 'Th', '13:00:00', '16:00:00', 'TBA', '2026-11-05 05:13:53'),
(6, 3, 'Fieldwork', 'MTh', '12:30:00', '14:30:00', 'TBA', '2026-11-05 05:16:12'),
(7, 5, 'Lecture', 'FS', '12:00:00', '13:00:00', 'TBA', '2026-11-05 06:38:08');

-- --------------------------------------------------------

--
-- Table structure for table `curriculum`
--

CREATE TABLE `curriculum` (
  `ID` int(11) NOT NULL,
  `ProgramID` int(11) NOT NULL,
  `CurriculumName` varchar(100) NOT NULL,
  `EffectiveYear` varchar(20) NOT NULL,
  `Description` text DEFAULT NULL,
  `IsActive` tinyint(1) DEFAULT 1,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `curriculum`
--

INSERT INTO `curriculum` (`ID`, `ProgramID`, `CurriculumName`, `EffectiveYear`, `Description`, `IsActive`, `CreatedAt`, `UpdatedAt`) VALUES
(7, 9, 'Revised BSIT Curriculum CMO No. 25, Series of 2015', '2015', 'The BS Information Technology program includes the study of the utilization of both hardware and software technologies involving planning, installing, customizing, operating, managing and administering, and maintaining information technology infrastructure that provides computing solutions to address the needs of an organization.\r\n\r\nThe program prepares graduates to address various user needs involving the selection, development, application, integration, and management of computing technologies within an organization.', 1, '2026-11-01 13:32:19', '2026-11-03 00:44:01');

-- --------------------------------------------------------

--
-- Table structure for table `curriculum_subjects`
--

CREATE TABLE `curriculum_subjects` (
  `ID` int(11) NOT NULL,
  `CurriculumID` int(11) NOT NULL,
  `SubjectID` int(11) DEFAULT NULL,
  `YearLevel` enum('1st Year','2nd Year','3rd Year','4th Year') DEFAULT NULL,
  `Semester` enum('1st Semester','2nd Semester','Summer') DEFAULT NULL,
  `CountTowardsTotal` tinyint(1) DEFAULT 1,
  `IsElective` tinyint(1) DEFAULT 0,
  `PrerequisiteLogic` enum('ALL','ANY') DEFAULT 'ALL',
  `IsPlaceholder` tinyint(1) DEFAULT 0,
  `PlaceholderTitle` varchar(50) DEFAULT 'Elective',
  `PlaceholderCode` varchar(20) DEFAULT 'ELECT',
  `Units` double(3,1) DEFAULT 3.0,
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `curriculum_subjects`
--

INSERT INTO `curriculum_subjects` (`ID`, `CurriculumID`, `SubjectID`, `YearLevel`, `Semester`, `CountTowardsTotal`, `IsElective`, `PrerequisiteLogic`, `IsPlaceholder`, `PlaceholderTitle`, `PlaceholderCode`, `Units`, `UpdatedAt`) VALUES
(1, 7, 1, '1st Year', '1st Semester', 1, 0, 'ALL', 0, NULL, NULL, 3.0, '2026-11-03 00:44:17'),
(2, 7, 2, '1st Year', '1st Semester', 1, 0, 'ALL', 0, NULL, NULL, 3.0, '2026-11-03 00:44:17'),
(3, 7, 3, '1st Year', '1st Semester', 1, 0, 'ALL', 0, NULL, NULL, 3.0, '2026-11-03 00:44:17'),
(4, 7, 4, '1st Year', '1st Semester', 1, 0, 'ALL', 0, NULL, NULL, 3.0, '2026-11-03 00:44:17'),
(5, 7, 5, '1st Year', '1st Semester', 1, 0, 'ALL', 0, NULL, NULL, 3.0, '2026-11-03 00:44:17'),
(6, 7, 6, '1st Year', '1st Semester', 1, 0, 'ALL', 0, NULL, NULL, 3.0, '2026-11-03 00:44:17'),
(8, 7, 8, '1st Year', '1st Semester', 1, 0, 'ALL', 0, '', '', 2.0, '2026-11-03 00:44:17'),
(9, 7, 9, '1st Year', '1st Semester', 0, 0, 'ALL', 0, NULL, NULL, 3.0, '2026-11-03 00:44:17'),
(10, 7, 10, '1st Year', '2nd Semester', 1, 0, 'ALL', 0, NULL, NULL, 3.0, '2026-11-03 00:44:17'),
(11, 7, 11, '1st Year', '2nd Semester', 1, 0, 'ALL', 0, NULL, NULL, 3.0, '2026-11-03 00:44:17'),
(12, 7, 12, '1st Year', '2nd Semester', 1, 0, 'ALL', 0, NULL, NULL, 3.0, '2026-11-03 00:44:17'),
(13, 7, 13, '1st Year', '2nd Semester', 1, 0, '', 0, NULL, NULL, 3.0, '2026-11-03 00:44:17'),
(14, 7, 14, '1st Year', '2nd Semester', 1, 0, 'ALL', 0, NULL, NULL, 3.0, '2026-11-03 00:44:17'),
(17, 7, 15, '1st Year', '2nd Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(18, 7, 16, '1st Year', '2nd Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(19, 7, 17, '1st Year', '2nd Semester', 1, 0, 'ALL', 0, '', '', 2.0, '2026-11-03 00:44:17'),
(21, 7, 18, '1st Year', '2nd Semester', 0, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(22, 7, 19, '2nd Year', '1st Semester', 1, 0, 'ALL', 0, NULL, NULL, 3.0, '2026-11-03 00:44:17'),
(23, 7, 20, '2nd Year', '1st Semester', 1, 0, 'ALL', 0, NULL, NULL, 3.0, '2026-11-03 00:44:17'),
(24, 7, NULL, '2nd Year', '1st Semester', 1, 0, 'ALL', 1, 'Foreign Language', 'Lang Elect', 3.0, '2026-11-03 00:44:17'),
(25, 7, 22, '2nd Year', '1st Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(26, 7, 23, '2nd Year', '1st Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(27, 7, 24, '2nd Year', '1st Semester', 1, 0, 'ALL', 0, NULL, NULL, 3.0, '2026-11-03 00:44:17'),
(28, 7, 25, '2nd Year', '1st Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(29, 7, 26, '2nd Year', '1st Semester', 1, 0, 'ALL', 0, '', '', 2.0, '2026-11-03 00:44:17'),
(30, 7, 27, '2nd Year', '2nd Semester', 1, 0, 'ALL', 0, NULL, NULL, 3.0, '2026-11-03 00:44:17'),
(32, 7, NULL, '2nd Year', '2nd Semester', 1, 0, 'ALL', 1, 'Technical Elective 1', 'Tech Elect', 3.0, '2026-11-03 00:44:17'),
(33, 7, 28, '2nd Year', '2nd Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(34, 7, 29, '2nd Year', '2nd Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(35, 7, 30, '2nd Year', '2nd Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(37, 7, 31, '2nd Year', '2nd Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(38, 7, 32, '2nd Year', '2nd Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(39, 7, 33, '2nd Year', '2nd Semester', 1, 0, 'ALL', 0, '', '', 2.0, '2026-11-03 00:44:17'),
(40, 7, 34, '3rd Year', '1st Semester', 1, 0, 'ALL', 0, NULL, NULL, 3.0, '2026-11-03 00:44:17'),
(41, 7, 35, '3rd Year', '1st Semester', 1, 0, 'ALL', 0, NULL, NULL, 3.0, '2026-11-03 00:44:17'),
(42, 7, 36, '3rd Year', '1st Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(43, 7, 37, '3rd Year', '1st Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(44, 7, 38, '3rd Year', '1st Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(45, 7, NULL, '3rd Year', '1st Semester', 1, 0, 'ANY', 1, 'Capstone Project 1 / Research Methods', 'IT191/IT198', 3.0, '2026-11-03 00:44:17'),
(49, 7, 41, '3rd Year', '2nd Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(50, 7, 42, '3rd Year', '2nd Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(51, 7, 43, '3rd Year', '2nd Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(52, 7, 44, '3rd Year', '2nd Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(53, 7, NULL, '3rd Year', '2nd Semester', 1, 0, 'ANY', 1, 'Capstone Project 2 / Undergraduate Thesis', 'IT192/199', 3.0, '2026-11-03 00:44:17'),
(56, 7, 47, '4th Year', '1st Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(57, 7, 48, '4th Year', '1st Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(58, 7, 49, '4th Year', '1st Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(59, 7, 50, '4th Year', '1st Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(62, 7, 51, '4th Year', '2nd Semester', 1, 0, 'ANY', 0, '', '', 6.0, '2026-11-03 00:44:17'),
(66, 7, 7, '1st Year', '1st Semester', 1, 0, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(67, 7, NULL, '3rd Year', '1st Semester', 1, 0, 'ALL', 1, 'Technical Elective 2', 'Tech Elect', 3.0, '2026-11-03 00:44:17'),
(68, 7, NULL, '3rd Year', '2nd Semester', 1, 0, 'ALL', 1, 'Technical Elective 3', 'Tech Elect', 3.0, '2026-11-03 00:44:17'),
(69, 7, NULL, '3rd Year', '2nd Semester', 1, 0, 'ALL', 1, 'Technical Elective 4', 'Tech Elect', 3.0, '2026-11-03 00:44:17'),
(70, 7, NULL, '4th Year', '1st Semester', 1, 0, 'ALL', 1, 'Technical Elective 5', 'Tech Elect', 3.0, '2026-11-03 00:44:17'),
(71, 7, NULL, '4th Year', '1st Semester', 1, 0, 'ALL', 1, 'Technical Elective 6', 'Tech Elect', 3.0, '2026-11-03 00:44:17'),
(72, 7, 52, '', '', 1, 1, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(73, 7, 53, '', '', 1, 1, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(74, 7, 55, '', '', 1, 1, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(75, 7, 56, '', '', 1, 1, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(76, 7, 57, '', '', 1, 1, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(77, 7, 58, '', '', 1, 1, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(78, 7, 59, '', '', 1, 1, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(79, 7, 60, '', '', 1, 1, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(80, 7, 61, '', '', 1, 1, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(81, 7, 62, '', '', 1, 1, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17'),
(82, 7, 63, '', '', 1, 1, 'ALL', 0, '', '', 3.0, '2026-11-03 00:44:17');

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `ID` int(11) NOT NULL,
  `StudentID` int(11) NOT NULL,
  `ClassID` int(11) NOT NULL,
  `Status` enum('Draft','Enrolled','Dropped','Withdrawn') DEFAULT 'Draft',
  `EnrolledOn` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`ID`, `StudentID`, `ClassID`, `Status`, `EnrolledOn`, `UpdatedAt`) VALUES
(13, 4, 2, 'Enrolled', '2026-11-05 07:37:37', '2026-11-05 07:37:37'),
(14, 4, 3, 'Enrolled', '2026-11-05 07:37:37', '2026-11-05 07:37:37'),
(15, 4, 5, 'Enrolled', '2026-11-05 07:37:37', '2026-11-05 07:37:37');

-- --------------------------------------------------------

--
-- Table structure for table `fee_assessments`
--

CREATE TABLE `fee_assessments` (
  `ID` int(11) NOT NULL,
  `StudentID` int(11) NOT NULL,
  `AcademicYearID` int(11) NOT NULL,
  `TuitionFee` decimal(10,2) NOT NULL,
  `LabFee` decimal(10,2) DEFAULT 0.00,
  `MiscellaneousFee` decimal(10,2) DEFAULT 0.00,
  `TotalAmount` decimal(10,2) NOT NULL,
  `PaymentStatus` enum('Unpaid','Partial','Paid') DEFAULT 'Unpaid',
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `offices`
--

CREATE TABLE `offices` (
  `ID` int(11) NOT NULL,
  `OfficeCode` varchar(20) NOT NULL,
  `OfficeName` varchar(150) NOT NULL,
  `IsActive` tinyint(1) DEFAULT 1,
  `OfficeHead` int(11) DEFAULT NULL,
  `Type` enum('Academic','Administrative') DEFAULT 'Administrative',
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `offices`
--

INSERT INTO `offices` (`ID`, `OfficeCode`, `OfficeName`, `IsActive`, `OfficeHead`, `Type`, `UpdatedAt`) VALUES
(1, 'BOT', 'Board of Trustees', 1, 37, 'Administrative', '2026-11-03 00:43:16'),
(2, 'BOTSec', 'Office of the Board Secretary', 1, 36, 'Administrative', '2026-11-03 00:43:16'),
(3, 'OP', 'Office of the President', 1, 37, 'Administrative', '2026-11-03 00:43:16'),
(4, 'Alumni', 'Alumni Association', 1, 67, 'Administrative', '2026-11-03 00:43:16'),
(5, 'GAD', 'Gender and Development Unit', 1, 27, 'Administrative', '2026-11-03 00:43:16'),
(6, 'ICTU', 'Information and Communications Technology Unit', 1, 17, 'Administrative', '2026-11-03 00:43:16'),
(7, 'IASU', 'Internal Audit Services Unit', 1, NULL, 'Administrative', '2026-11-03 00:43:16'),
(8, 'Legal', 'Legal Services Unit', 1, NULL, 'Administrative', '2026-11-03 00:43:16'),
(9, 'OVPAA', 'Office of the Vice President for Academic Affairs', 1, 61, 'Administrative', '2026-11-03 00:43:16'),
(10, 'Admissions', 'Admission and Guidance Counseling Services Unit', 1, 25, 'Administrative', '2026-11-03 00:43:16'),
(11, 'DSA', 'Division of Student Affairs', 1, 64, 'Administrative', '2026-11-03 00:43:16'),
(12, 'HSU', 'Health Services Unit', 1, 48, 'Administrative', '2026-11-03 00:43:16'),
(13, 'Library', 'Library Services', 1, 49, 'Administrative', '2026-11-03 00:43:16'),
(14, 'NSTP', 'National Service Training Program', 1, 63, 'Administrative', '2026-11-03 00:43:16'),
(15, 'Registrar', 'Registrar Unit', 1, 24, 'Administrative', '2026-11-03 00:43:16'),
(16, 'Sports', 'Sports and Wellness Development Unit', 1, 38, 'Administrative', '2026-11-03 00:43:16'),
(18, 'COA', 'Commission on Audit - AMSC', 1, 62, 'Administrative', '2026-11-03 00:43:16'),
(19, 'CA', 'College of Agriculture', 1, 15, 'Academic', '2026-11-03 00:43:16'),
(20, 'CBA', 'College of Business Administration', 1, 27, 'Academic', '2026-11-03 00:43:16'),
(21, 'CED', 'College of Education', 1, 11, 'Academic', '2026-11-03 00:43:16'),
(22, 'CFI', 'College of Fisheries', 1, 66, 'Academic', '2026-11-03 00:43:16'),
(23, 'CFO', 'College of Forestry', 1, 65, 'Academic', '2026-11-03 00:43:16'),
(24, 'CIT', 'College of Information Technology', 1, 2, 'Academic', '2026-11-03 00:43:16'),
(25, 'GRAD', 'Graduate School', 1, 18, 'Academic', '2026-11-03 00:43:16'),
(26, 'OSAO', 'Office of the Supervising Administrative Officer', 1, 52, 'Administrative', '2026-11-03 00:43:16'),
(27, 'ESU', 'Extension Services Unit', 1, 9, 'Administrative', '2026-11-03 00:43:16'),
(28, 'RDU', 'Research and Development Unit', 1, 51, 'Administrative', '2026-11-03 00:43:16'),
(29, 'PDU', 'Project and Development Unit', 1, 56, 'Administrative', '2026-11-03 00:43:16'),
(30, 'PLAN', 'Planning Unit', 1, 69, 'Administrative', '2026-11-03 00:43:16'),
(31, 'ACCT', 'Accounting Unit', 1, 33, 'Administrative', '2026-11-03 00:43:16'),
(32, 'BUDGET', 'Budget Unit', 1, 50, 'Administrative', '2026-11-03 00:43:16'),
(33, 'DISBURSE', 'Disbursing Unit', 1, 52, 'Administrative', '2026-11-03 00:43:16'),
(34, 'GSU', 'General Services Unit', 1, 6, 'Administrative', '2026-11-03 00:43:16'),
(35, 'HRMU', 'Human Resource Management Unit', 1, 14, 'Administrative', '2026-11-03 00:43:16'),
(36, 'IU', 'Information Unit', 1, 47, 'Administrative', '2026-11-03 00:43:16'),
(37, 'SSU', 'Security Services Unit', 1, 69, 'Administrative', '2026-11-03 00:43:16'),
(38, 'SPMU', 'Supply and Property Management Unit', 1, 67, 'Administrative', '2026-11-03 00:43:16'),
(39, 'OVPAF', 'Office of the Vice President for Administration and Finance', 1, 9, 'Administrative', '2026-11-03 00:43:16'),
(40, 'CRIM', 'College of Criminology', 1, NULL, 'Academic', '2026-11-03 00:43:16');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `ID` int(11) NOT NULL,
  `AssessmentID` int(11) NOT NULL,
  `ORNumber` varchar(50) DEFAULT NULL,
  `AmountPaid` decimal(10,2) NOT NULL,
  `PaymentMethod` enum('Cash','Online Payment','Bank Transfer') NOT NULL,
  `ProofOfPaymentURL` varchar(255) DEFAULT NULL,
  `VerifiedByStaffID` int(11) DEFAULT NULL,
  `PaymentDate` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `ID` int(11) NOT NULL,
  `ProgramCode` varchar(20) NOT NULL,
  `ProgramName` varchar(150) NOT NULL,
  `IsActive` tinyint(1) DEFAULT 1,
  `CollegeID` int(11) NOT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`ID`, `ProgramCode`, `ProgramName`, `IsActive`, `CollegeID`, `CreatedAt`, `UpdatedAt`) VALUES
(2, 'BSCRIM', 'Bachelor of Science in Criminology', 1, 40, '2026-11-01 10:29:05', '2026-11-03 00:43:39'),
(4, 'BSBA', 'Bachelor of Science in Business Administration (Human Resource Management)', 1, 20, '2026-11-01 10:29:05', '2026-11-03 00:43:39'),
(5, 'BEED', 'Bachelor of Elementary Education (General Education)', 1, 21, '2026-11-01 10:29:05', '2026-11-03 00:43:39'),
(6, 'BSED', 'Bachelor of Secondary Education (Social Studies)', 1, 21, '2026-11-01 10:29:05', '2026-11-03 00:43:39'),
(7, 'BSFish', 'Bachelor of Science in Fisheries', 1, 22, '2026-11-01 10:29:05', '2026-11-03 00:43:39'),
(8, 'BSForest', 'Bachelor of Science in Forestry', 1, 23, '2026-11-01 10:29:05', '2026-11-03 00:43:39'),
(9, 'BSIT', 'Bachelor of Science in Information Technology', 1, 24, '2026-11-01 10:29:05', '2026-11-03 00:43:39'),
(10, 'CPT', 'Certificate in Professional Teaching', 1, 25, '2026-11-01 10:29:05', '2026-11-03 00:43:39'),
(11, 'MAED', 'Master of Arts in Education (Educational Management)', 1, 25, '2026-11-01 10:29:05', '2026-11-03 00:43:39'),
(12, 'BSA', 'Bachelor of Science in Agriculture (Farming System)', 1, 19, '2026-11-01 12:28:45', '2026-11-03 00:43:39');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `ID` int(11) NOT NULL,
  `RoleName` varchar(50) NOT NULL,
  `Description` varchar(255) DEFAULT NULL,
  `Module` enum('admin','applicant') DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`ID`, `RoleName`, `Description`, `Module`) VALUES
(1, 'Administrator', 'System Admin', 'admin'),
(2, 'Applicant', 'Prospective Student', 'applicant'),
(3, 'Student', 'Enrolled Student', 'applicant');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `ID` int(11) NOT NULL,
  `AcademicYearID` int(11) NOT NULL,
  `SectionCode` varchar(50) NOT NULL,
  `MaxCapacity` int(11) DEFAULT 40,
  `CurrentEnrolled` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`ID`, `AcademicYearID`, `SectionCode`, `MaxCapacity`, `CurrentEnrolled`) VALUES
(3, 2, 'BSIT Green Goblins', 40, 0);

-- --------------------------------------------------------

--
-- Table structure for table `staff_designations`
--

CREATE TABLE `staff_designations` (
  `ID` int(11) NOT NULL,
  `StaffID` int(11) NOT NULL,
  `OfficeID` int(11) DEFAULT NULL,
  `JobTitle` varchar(100) NOT NULL,
  `AppointmentType` enum('Academic','Administrative') NOT NULL,
  `IsPrimary` tinyint(1) DEFAULT 0,
  `StartDate` date DEFAULT NULL,
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `staff_designations`
--

INSERT INTO `staff_designations` (`ID`, `StaffID`, `OfficeID`, `JobTitle`, `AppointmentType`, `IsPrimary`, `StartDate`, `UpdatedAt`) VALUES
(3, 37, 3, 'President', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(4, 36, 2, 'Head', 'Administrative', 0, '2026-11-01', '2026-11-03 00:43:32'),
(5, 36, 1, 'Board Secretary', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(6, 61, 9, 'Vice President for Academic Affairs', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(7, 62, 18, 'State Auditor', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(8, 41, 10, 'Guidance Counselor (College)', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(9, 27, 20, 'Dean', 'Academic', 1, '2026-11-01', '2026-11-03 00:43:32'),
(10, 27, 5, 'Focal Person', 'Administrative', 0, '2026-11-01', '2026-11-03 00:43:32'),
(11, 67, 1, 'Alumni Trustee', 'Administrative', 0, '2026-11-01', '2026-11-03 00:43:32'),
(12, 67, 4, 'President', 'Administrative', 0, '2026-11-01', '2026-11-03 00:43:32'),
(13, 68, 37, 'Warden', 'Administrative', 0, '2026-11-01', '2026-11-03 00:43:32'),
(14, 37, 1, 'Vice Chairperson', 'Administrative', 0, '2026-11-01', '2026-11-03 00:43:32'),
(15, 9, 39, 'Vice President', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(16, 9, 27, 'Head', 'Administrative', 0, '2026-11-01', '2026-11-03 00:43:32'),
(17, 15, 19, 'Dean', 'Academic', 1, '2026-11-01', '2026-11-03 00:43:32'),
(18, 11, 21, 'Dean', 'Academic', 1, '2026-11-01', '2026-11-03 00:43:32'),
(19, 66, 22, 'Dean', 'Academic', 1, '2026-11-01', '2026-11-03 00:43:32'),
(20, 65, 23, 'Dean', 'Academic', 1, '2026-11-01', '2026-11-03 00:43:32'),
(21, 18, 25, 'Dean', 'Academic', 1, '2026-11-01', '2026-11-03 00:43:32'),
(22, 25, 10, 'Guidance Counselor (High School)', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(23, 64, 11, 'Director', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(24, 48, 12, 'School Nurse', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(25, 49, 13, 'Librarian', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(26, 63, 14, 'Commandant', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(27, 24, 15, 'OIC Registrar', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(28, 38, 16, 'Head', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(29, 17, 6, 'Head', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(30, 52, 33, 'Disbursement Officer', 'Administrative', 0, '2026-11-01', '2026-11-03 00:43:32'),
(31, 52, 26, 'Head', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(32, 51, 28, 'Director', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(33, 69, 30, 'Head', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(34, 56, 29, 'Head', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(35, 33, 31, 'Accountant', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(36, 50, 32, 'Head', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(37, 6, 34, 'Head', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(38, 14, 35, 'Chief HRMO', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(39, 47, 36, 'Head', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(40, 67, 38, 'Supply Officer', 'Administrative', 1, '2026-11-01', '2026-11-03 00:43:32'),
(41, 2, 24, 'Dean', 'Academic', 1, '2026-11-04', '2026-11-04 13:49:32');

-- --------------------------------------------------------

--
-- Table structure for table `staff_members`
--

CREATE TABLE `staff_members` (
  `StaffID` int(11) NOT NULL,
  `UserID` int(11) DEFAULT NULL,
  `EmployeeNumber` varchar(30) NOT NULL,
  `FirstName` varchar(50) NOT NULL,
  `MiddleName` varchar(50) DEFAULT NULL,
  `LastName` varchar(50) NOT NULL,
  `ExtName` enum('Jr.','Sr.','II','III','IV','V','VI') DEFAULT NULL,
  `ContactNumber` varchar(20) DEFAULT NULL,
  `Gender` enum('Male','Female') NOT NULL,
  `EmploymentStatus` enum('Full-Time','Part-Time','Contractual') DEFAULT 'Full-Time',
  `IsActive` tinyint(1) DEFAULT 1,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `DateOfBirth` date DEFAULT NULL,
  `HomeAddress` text DEFAULT NULL,
  `ProfilePicture` varchar(255) DEFAULT NULL,
  `PositionTitle` varchar(100) DEFAULT NULL,
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `staff_members`
--

INSERT INTO `staff_members` (`StaffID`, `UserID`, `EmployeeNumber`, `FirstName`, `MiddleName`, `LastName`, `ExtName`, `ContactNumber`, `Gender`, `EmploymentStatus`, `IsActive`, `CreatedAt`, `DateOfBirth`, `HomeAddress`, `ProfilePicture`, `PositionTitle`, `UpdatedAt`) VALUES
(1, 1, 'AMSC-EMP-20260001', 'Ahmad Kiram', 'Alonto', 'Grar', '', '09350229142', 'Male', 'Part-Time', 1, '2026-10-30 10:47:04', '1994-12-04', 'Cong. Lucman Street, Brgy. Moncado Kadingilan, Marawi City', 'dkboystudios-admin-20261101030030.jpeg', NULL, '2026-11-03 00:42:56'),
(2, 3, 'AMSC-EMP-20260002', 'Amrollah', 'P.', 'Dianalan', '', '0909 435 3904', 'Male', 'Contractual', 1, '2026-10-31 05:47:02', '2026-10-31', 'Ditsaan Ramain, Lanao del Sur', 'amrollah_dianalan-admin-20261101082718.webp', 'Professor', '2026-11-03 00:42:56'),
(6, NULL, 'AMSC-EMP-20260006', 'Alinor', 'Cali', 'Abdulazis', '', '', 'Male', 'Contractual', 1, '2026-11-01 06:28:23', '0000-00-00', '', 'alinor_abdulazis-admin-20261101072823.webp', 'General Services Officer V', '2026-11-03 07:44:53'),
(7, NULL, 'AMSC-EMP-20260007', 'Hamzah', 'C.', 'Abdullah', '', '', 'Male', 'Full-Time', 1, '2026-11-01 06:29:48', '0000-00-00', '', 'hamzah_abdullah-admin-20261101072948.webp', 'Associate Professor I', '2026-11-03 00:42:56'),
(8, NULL, 'AMSC-EMP-20260008', 'Abdulhakim', 'M..', 'Abdulrahman', '', '', 'Male', 'Full-Time', 1, '2026-11-01 06:33:34', '0000-00-00', '', 'abdulhakim_abdulrahman-admin-20261101073334.webp', 'Planning Officer I', '2026-11-03 00:42:56'),
(9, NULL, 'AMSC-EMP-20260009', 'Rashida', 'A.', 'Abedin', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:34:52', '0000-00-00', '', 'rashida_abedin-admin-20261101073452.webp', 'Chief Administrative Officer', '2026-11-03 00:42:56'),
(10, NULL, 'AMSC-EMP-20260010', 'Razimah', 'A.', 'Abedin', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:35:33', '0000-00-00', '', 'razimah_abedin-admin-20261101073624.webp', 'Administrative Officer II', '2026-11-03 00:42:56'),
(11, NULL, 'AMSC-EMP-20260011', 'Sittienor', 'C.', 'Abubakar', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:37:11', '0000-00-00', '', 'sittienor_abubakar-admin-20261101073711.webp', 'Associate Professor IV', '2026-11-03 00:42:56'),
(12, NULL, 'AMSC-EMP-20260012', 'Juhaina', 'A.', 'Adiong', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:37:48', '0000-00-00', '', 'juhaina_adiong-admin-20261101073748.webp', 'Administrative Assistant II', '2026-11-03 00:42:56'),
(13, NULL, 'AMSC-EMP-20260013', 'Mohammad Jarir', 'M.', 'Adiong', '', '', 'Male', 'Full-Time', 1, '2026-11-01 06:38:27', '0000-00-00', '', 'mohammad_jarir_adiong-admin-20261101073827.webp', 'Guidance Counselor I', '2026-11-03 00:42:56'),
(14, NULL, 'AMSC-EMP-20260014', 'Saima', 'U.', 'Adiong', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:39:22', '0000-00-00', '', 'saima_adiong-admin-20261101073935.webp', 'Chief Human Resource Management Officer', '2026-11-03 00:42:56'),
(15, NULL, 'AMSC-EMP-20260015', 'Samiah', 'M.', 'Adiong', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:40:21', '0000-00-00', '', 'samiah_adiong-admin-20261101074021.webp', 'Instructor I', '2026-11-03 00:42:56'),
(16, NULL, 'AMSC-EMP-20260016', 'Abdul Khaliq', 'B.', 'Ahmad', '', '', 'Male', 'Contractual', 1, '2026-11-01 06:41:14', '0000-00-00', '', 'abdul_khaliq_ahmad-admin-20261101074114.webp', 'Contract of Service', '2026-11-03 00:42:56'),
(17, NULL, 'AMSC-EMP-20260017', 'Junaid', 'Bayabao', 'Ahmad', '', '', 'Male', 'Full-Time', 1, '2026-11-01 06:43:35', '0000-00-00', '', 'junaid_ahmad-admin-20261101074335.webp', 'Information Technology Officer I', '2026-11-03 00:42:56'),
(18, NULL, 'AMSC-EMP-20260018', 'Bedaria', 'I.', 'Amerol', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:44:11', '0000-00-00', '', 'bedaria_amerol-admin-20261101074411.webp', 'Associate Professor IV', '2026-11-03 00:42:56'),
(19, NULL, 'AMSC-EMP-20260019', 'Nur-ainie Rema', 'B.', 'Andig', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:44:49', '0000-00-00', '', 'nur-ainie_rema_andig-admin-20261101074449.webp', 'Information Officer', '2026-11-03 00:42:56'),
(20, NULL, 'AMSC-EMP-20260020', 'Noraisa', 'A', 'Bangadan', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:45:36', '0000-00-00', '', 'noraisa_bangadan-admin-20261101074536.webp', 'Administrative Officer I', '2026-11-03 00:42:56'),
(21, NULL, 'AMSC-EMP-20260021', 'Amirah', 'P.', 'Banocag', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:46:13', '0000-00-00', '', 'amirah_banocag-admin-20261101074613.webp', 'Administrative Officer II', '2026-11-03 00:42:56'),
(22, NULL, 'AMSC-EMP-20260022', 'Sittie Najmah', 'A.', 'Basher', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:46:58', '0000-00-00', '', 'sittie_najmah_basher-admin-20261101074658.webp', 'Administrative Aide VI', '2026-11-03 00:42:56'),
(23, NULL, 'AMSC-EMP-20260023', 'Bainarie Merpha', '', 'Bayabao', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:47:33', '0000-00-00', '', 'bainarie_merpha_bayabao-admin-20261101074733.webp', 'Instructor I', '2026-11-03 00:42:56'),
(24, NULL, 'AMSC-EMP-20260024', 'Yashly Hanna', 'Adiong', 'Bayabao', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:48:12', '0000-00-00', '', 'yashly_hanna_bayabao-admin-20261101074812.webp', 'College Registrar', '2026-11-03 00:42:56'),
(25, NULL, 'AMSC-EMP-20260025', 'Riza Dael', '', 'Bongcarawan', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:48:59', '0000-00-00', '', 'riza_dael_bongcarawan-admin-20261101074859.webp', 'Guidance Counselor', '2026-11-03 00:42:56'),
(26, NULL, 'AMSC-EMP-20260026', 'Donna-Kate', 'A.', 'Bustamante', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:49:35', '0000-00-00', '', 'donna-kate_bustamante-admin-20261101074935.webp', 'Administrative Aide VI', '2026-11-03 00:42:56'),
(27, NULL, 'AMSC-EMP-20260027', 'Jamaliah', 'A', 'Casidar', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:50:22', '0000-00-00', '', 'jamaleah_casidar-admin-20261101075022.webp', 'Instructor I', '2026-11-03 00:42:56'),
(28, NULL, 'AMSC-EMP-20260028', 'Pindoraga', 'M.', 'Diamla', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:50:57', '0000-00-00', '', 'pindoraga_diamla-admin-20261101075057.webp', 'Administrative Officer V', '2026-11-03 00:42:56'),
(29, NULL, 'AMSC-EMP-20260029', 'Norhaya', 'L.', 'Dipatuan', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:51:33', '0000-00-00', '', 'norhaya_dipatuan-admin-20261101075133.webp', 'Associate Professor V', '2026-11-03 00:42:56'),
(30, NULL, 'AMSC-EMP-20260030', 'Norania', 'N.', 'Disinggun', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:52:14', '0000-00-00', '', 'norania_disinggun-admin-20261101075214.webp', 'Associate Professor III', '2026-11-03 00:42:56'),
(31, NULL, 'AMSC-EMP-20260031', 'Imanhanna', 'Macalimpao', 'Goling', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:52:51', '0000-00-00', '', 'imanhanna_goling-admin-20261101075251.webp', 'Accountant II', '2026-11-03 00:42:56'),
(32, NULL, 'AMSC-EMP-20260032', 'Wahida', 'B.', 'Guialal', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:53:32', '0000-00-00', '', 'wahida_guialal-admin-20261101075332.webp', 'Associate Professor I', '2026-11-03 00:42:56'),
(33, NULL, 'AMSC-EMP-20260033', 'Sittie Saihana', 'Salic', 'Hadji Ali', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:54:06', '0000-00-00', '', 'sittie_saihana_hadji_ali-admin-20261101075406.webp', 'Accountant III', '2026-11-03 00:42:56'),
(34, NULL, 'AMSC-EMP-20260034', 'Khalid', 'A.', 'Ibrahim', '', '', 'Male', 'Full-Time', 1, '2026-11-01 06:54:44', '0000-00-00', '', 'khalid_ibrahim-admin-20261101075444.webp', 'Administrative Officer III', '2026-11-03 00:42:56'),
(35, NULL, 'AMSC-EMP-20260035', 'Amidah', 'T.', 'Inidal', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:55:37', '0000-00-00', '', 'amidah_inidal-admin-20261101075537.webp', 'Administrative Aide VI', '2026-11-03 00:42:56'),
(36, NULL, 'AMSC-EMP-20260036', 'Sherifa Ainee', 'O.', 'Kadil', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:56:18', '0000-00-00', '', 'sherifa_ainee_kadil-admin-20261101075618.webp', 'Board Secretary V', '2026-11-03 00:42:56'),
(37, NULL, 'AMSC-EMP-20260037', 'Sherifa Rohannie', 'O.', 'Kadil-Adiong', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:57:00', '0000-00-00', '', 'sherifa_rohannie_kadil-adiong-admin-20261101075700.webp', 'SUC President I', '2026-11-03 00:42:56'),
(38, NULL, 'AMSC-EMP-20260038', 'Victoria', '', 'Langco', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:57:55', '0000-00-00', '', 'victoria_langco-admin-20261101075755.webp', 'Associate Professor I', '2026-11-03 00:42:56'),
(39, NULL, 'AMSC-EMP-20260039', 'Emmirhama', 'P.', 'Macabando', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:58:29', '0000-00-00', '', 'emmirhama_macabando-admin-20261101075829.webp', 'Attorney IV', '2026-11-03 00:42:56'),
(40, NULL, 'AMSC-EMP-20260040', 'Abuat', 'S.', 'Macarampat', '', '', 'Male', 'Full-Time', 1, '2026-11-01 06:59:02', '0000-00-00', '', 'abuat_macarampat-admin-20261101075902.webp', 'Administrative Officer IV', '2026-11-03 00:42:56'),
(41, NULL, 'AMSC-EMP-20260041', 'Hidaya', 'Cuaro', 'Magator', '', '', 'Female', 'Full-Time', 1, '2026-11-01 06:59:50', '0000-00-00', '', 'hidaya_magator-admin-20261101075950.webp', 'Guidance Counselor', '2026-11-03 00:42:56'),
(42, NULL, 'AMSC-EMP-20260042', 'Normalah', 'S', 'Manda', '', '', 'Female', 'Full-Time', 1, '2026-11-01 07:00:29', '0000-00-00', '', 'normalah_manda-admin-20261101080029.webp', 'Administrative Officer III', '2026-11-03 00:42:56'),
(43, NULL, 'AMSC-EMP-20260043', 'Abdul Rafie', 'Ramos', 'Masbud', '', '', 'Male', 'Full-Time', 1, '2026-11-01 07:01:05', '0000-00-00', '', 'abdul_rafie_masbud-admin-20261101080105.webp', 'Information Officer II', '2026-11-03 00:42:56'),
(44, NULL, 'AMSC-EMP-20260044', 'Karina', 'M.', 'Mauna', '', '', 'Female', 'Full-Time', 1, '2026-11-01 07:01:39', '0000-00-00', '', 'karina_mauna-admin-20261101080139.webp', 'Associate Professor V', '2026-11-03 00:42:56'),
(45, NULL, 'AMSC-EMP-20260045', 'Jamael', 'B.', 'Maunte', '', '', 'Male', 'Full-Time', 1, '2026-11-01 07:02:16', '0000-00-00', '', 'jamael_maunte-admin-20261101080216.webp', 'Associate Professor III', '2026-11-03 00:42:56'),
(46, NULL, 'AMSC-EMP-20260046', 'Monera', 'M.', 'Mimbalawag', '', '', 'Female', 'Full-Time', 1, '2026-11-01 07:02:56', '0000-00-00', '', 'monera_mimbalawag-admin-20261101080256.webp', 'Administrative Officer IV', '2026-11-03 00:42:56'),
(47, NULL, 'AMSC-EMP-20260047', 'Aliasgar', 'M.', 'Mimbalawag', '', '', 'Male', 'Full-Time', 1, '2026-11-01 07:03:31', '0000-00-00', '', 'aliasgar_mimbalawag-admin-20261101080331.webp', 'Information Officer III', '2026-11-03 00:42:56'),
(48, NULL, 'AMSC-EMP-20260048', 'Janiffa', 'P.', 'Mutin', '', '', 'Female', 'Full-Time', 1, '2026-11-01 07:04:15', '0000-00-00', '', 'janiffa_mutin-admin-20261101080415.webp', 'School Nurse', '2026-11-03 00:42:56'),
(49, NULL, 'AMSC-EMP-20260049', 'Amer Hassan', 'M.', 'Pacaluna', '', '', 'Male', 'Full-Time', 1, '2026-11-01 07:04:58', '0000-00-00', '', 'amer_hassan_pacaluna-admin-20261101080458.webp', 'Librarian III', '2026-11-03 00:42:56'),
(50, NULL, 'AMSC-EMP-20260050', 'Al-Mohiden', 'M.', 'Pandapatan', '', '', 'Male', 'Full-Time', 1, '2026-11-01 07:05:35', '0000-00-00', '', 'al-mohiden_pandapatan-admin-20261101080535.webp', 'Administrative Officer V', '2026-11-03 00:42:56'),
(51, NULL, 'AMSC-EMP-20260051', 'Alliah', 'A.', 'Pandapatan', '', '', 'Female', 'Full-Time', 1, '2026-11-01 07:06:07', '0000-00-00', '', 'alliah_pandapatan-admin-20261101080607.webp', 'Instructor I', '2026-11-03 00:42:56'),
(52, NULL, 'AMSC-EMP-20260052', 'Salam', 'M..', 'Pandapatan', '', '', 'Female', 'Full-Time', 1, '2026-11-01 07:06:45', '0000-00-00', '', 'salam_pandapatan-admin-20261101080645.webp', 'Supervising Administrative Officer', '2026-11-03 00:42:56'),
(53, NULL, 'AMSC-EMP-20260053', 'Samsida', 'A.', 'Pandapatan', '', '', 'Female', 'Full-Time', 1, '2026-11-01 07:07:31', '0000-00-00', '', 'samsida_pandapatan-admin-20261101080731.webp', 'Administrative Officer II', '2026-11-03 00:42:56'),
(54, NULL, 'AMSC-EMP-20260054', 'Sanussi', 'A.', 'Pandapatan', '', '', 'Male', 'Full-Time', 1, '2026-11-01 07:08:05', '0000-00-00', '', 'sanussi_pandapatan-admin-20261101080805.webp', 'Associate Professor III', '2026-11-03 00:42:56'),
(55, NULL, 'AMSC-EMP-20260055', 'Joainah', 'A.', 'Paunte', '', '', 'Female', 'Full-Time', 1, '2026-11-01 07:09:20', '0000-00-00', '', 'joainah_paunte-admin-20261101080920.webp', 'Administrative Officer I', '2026-11-03 00:42:56'),
(56, NULL, 'AMSC-EMP-20260056', 'Nizar', 'Adiong', 'Sacar', '', '', 'Male', 'Full-Time', 1, '2026-11-01 07:10:00', '0000-00-00', '', 'nizar_sacar-admin-20261101081000.webp', 'Project Development Officer III', '2026-11-03 00:42:56'),
(57, NULL, 'AMSC-EMP-20260057', 'Orandaya', 'M.', 'Sacar', '', '', 'Male', 'Full-Time', 1, '2026-11-01 07:10:28', '0000-00-00', '', 'orandaya_sacar-admin-20261101081028.webp', 'Internal Auditor II', '2026-11-03 00:42:56'),
(58, NULL, 'AMSC-EMP-20260058', 'Jonaimah', 'A.', 'Sarip', '', '', 'Female', 'Full-Time', 1, '2026-11-01 07:11:00', '0000-00-00', '', 'jonaimah_sarip-admin-20261101081100.webp', 'Administrative Aide VI', '2026-11-03 00:42:56'),
(59, NULL, 'AMSC-EMP-20260059', 'Junaid', 'B.', 'Saripada', '', '', 'Male', 'Full-Time', 1, '2026-11-01 07:11:40', '0000-00-00', '', 'junaid_saripada-admin-20261101081140.webp', 'Administrative Officer IV', '2026-11-03 00:42:56'),
(60, NULL, 'AMSC-EMP-20260060', 'Doblon', 'S.', 'Uronga-Mala', '', '', 'Female', 'Full-Time', 1, '2026-11-01 07:12:27', '0000-00-00', '', 'doblon_uronga-mala-admin-20261101081227.webp', 'Administrative Officer I', '2026-11-03 00:42:56'),
(61, NULL, 'AMSC-EMP-20260061', 'Hanya', '', 'Adiong', '', '', 'Female', 'Full-Time', 1, '2026-11-01 07:16:59', '0000-00-00', '', 'hanya_adiong-admin-20261101081659.webp', 'Vice President for Academic Affairs', '2026-11-03 00:42:56'),
(62, NULL, 'AMSC-EMP-20260062', 'Ameladin', '', 'Polao', '', '', 'Male', 'Full-Time', 1, '2026-11-01 07:18:38', '0000-00-00', '', 'ameladin_polao-admin-20261101081838.webp', 'State Auditor', '2026-11-03 00:42:56'),
(63, NULL, 'AMSC-EMP-20260063', 'Jonathan', '', 'Magarang', '', '', 'Male', 'Full-Time', 1, '2026-11-01 07:33:27', '0000-00-00', '', 'jonathan_magarang-admin-20261101083327.webp', 'Commandant', '2026-11-03 00:42:56'),
(64, NULL, 'AMSC-EMP-20260064', 'Luis', '', 'Adiong', '', '', 'Male', 'Full-Time', 1, '2026-11-01 07:34:01', '0000-00-00', '', 'luis_adiong-admin-20261101083401.webp', 'Director', '2026-11-03 00:42:56'),
(65, NULL, 'AMSC-EMP-20260065', 'Norodin', '', 'Adiong', '', '', 'Male', 'Full-Time', 1, '2026-11-01 07:34:38', '0000-00-00', '', 'norodin_adiong-admin-20261101083438.webp', 'Professor', '2026-11-03 00:42:56'),
(66, NULL, 'AMSC-EMP-20260066', 'Jahirun', '', 'Mungkon', '', '', 'Male', 'Full-Time', 1, '2026-11-01 07:35:11', '0000-00-00', '', 'jahirun_mungkon-admin-20261101083511.webp', 'Professor', '2026-11-03 00:42:56'),
(67, NULL, 'AMSC-EMP-20260067', 'Mocmina', '', 'Usman', '', '', 'Female', 'Full-Time', 1, '2026-11-01 07:47:49', '0000-00-00', '', 'mocmina_usman-admin-20261101084749.webp', 'Supply Officer', '2026-11-03 00:42:56'),
(68, NULL, 'AMSC-EMP-20260068', 'Levi', 'Salih', 'Adiong', '', '', 'Male', 'Full-Time', 1, '2026-11-01 07:53:21', '0000-00-00', '', 'levi_adiong-admin-20261101085321.webp', 'Warden', '2026-11-03 00:42:56'),
(69, NULL, 'AMSC-EMP-20260069', 'Anuar', '', 'Amerol', '', '', 'Male', 'Full-Time', 1, '2026-11-01 08:00:16', '0000-00-00', '', 'anuar_amerol-admin-20261101090016.webp', 'Administrative Officer', '2026-11-03 00:42:56');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `StudentID` int(11) NOT NULL,
  `ApplicantID` int(11) NOT NULL,
  `StudentNumber` varchar(30) NOT NULL,
  `ProgramID` int(11) NOT NULL,
  `CurriculumID` int(11) NOT NULL,
  `EnrollmentStatus` enum('Pre-Enrolled','Enrolled','Dropped','Graduated') DEFAULT 'Pre-Enrolled',
  `DateEnrolled` date DEFAULT NULL,
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`StudentID`, `ApplicantID`, `StudentNumber`, `ProgramID`, `CurriculumID`, `EnrollmentStatus`, `DateEnrolled`, `UpdatedAt`) VALUES
(4, 1, '20260004', 9, 7, 'Enrolled', NULL, '2026-11-05 09:01:50');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `ID` int(11) NOT NULL,
  `SubjectCode` varchar(20) NOT NULL,
  `SubjectTitle` varchar(150) NOT NULL,
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`ID`, `SubjectCode`, `SubjectTitle`, `UpdatedAt`) VALUES
(1, 'GEC101', 'Understanding the Self', '2026-11-03 00:44:09'),
(2, 'GEC102', 'Purposive Communication', '2026-11-03 00:44:09'),
(3, 'MAT104', 'Discrete Mathematics', '2026-11-03 00:44:09'),
(4, 'GEC104', 'Mathematics in the Modern World', '2026-11-03 00:44:09'),
(5, 'IT100', 'Fundamentals of Computing', '2026-11-03 00:44:09'),
(6, 'IT101', 'Computer Programming I', '2026-11-03 00:44:09'),
(7, 'ARABIC101', 'Basic Arabic and Writing', '2026-11-03 00:44:09'),
(8, 'PED001', 'Exercise Prescription and Management', '2026-11-03 00:44:09'),
(9, 'NSTP101', 'National Service Training Program 1', '2026-11-03 00:44:09'),
(10, 'GEC103', 'The Contemporary World', '2026-11-03 00:44:09'),
(11, 'GEC105', 'Readings in Philippine History', '2026-11-03 00:44:09'),
(12, 'GEC106', 'Art Appreciation', '2026-11-03 00:44:09'),
(13, 'FPE101', 'Fundamental of Peace Education', '2026-11-03 00:44:09'),
(14, 'MAT051', 'Calculus I', '2026-11-03 00:44:09'),
(15, 'IT102', 'Computer Programming II', '2026-11-03 00:44:09'),
(16, 'ARABIC102', 'Lives and Works of Prophets', '2026-11-03 00:44:09'),
(17, 'PED002', 'Dance/Martial Arts', '2026-11-03 00:44:09'),
(18, 'NSTP102', 'National Service Training Program 2', '2026-11-03 00:44:09'),
(19, 'GEC107', 'Ethics', '2026-11-03 00:44:09'),
(20, 'GEC108', 'Science, Technology and Society', '2026-11-03 00:44:09'),
(22, 'MAT061', 'Calculus II', '2026-11-03 00:44:09'),
(23, 'IT121', 'Data Structures and Algorithms', '2026-11-03 00:44:09'),
(24, 'IT131', 'Computer Architecture and Operating Systems', '2026-11-03 00:44:09'),
(25, 'ARABIC103', 'Islamic Da&#039;wah', '2026-11-03 00:44:09'),
(26, 'PED003', 'Individual/Dual Sports/Traditional/Recreational Games', '2026-11-03 00:44:09'),
(27, 'HIS003', 'History of Muslim Filipinos of the Muslim and Indigenous People of the MINSUPALA', '2026-11-03 00:44:09'),
(28, 'MAT071', 'Probability and Statistical Inference for Computing Systems', '2026-11-03 00:44:09'),
(29, 'IT125', 'Introduction to Human Computer Interaction', '2026-11-03 00:44:09'),
(30, 'IT132', 'Fundamentals of Computer Networks', '2026-11-03 00:44:09'),
(31, 'IT151', 'Information Management', '2026-11-03 00:44:09'),
(32, 'ARABIC104', 'Islamic Laws', '2026-11-03 00:44:09'),
(33, 'PED004', 'Team Sports', '2026-11-03 00:44:09'),
(34, 'GEC109', 'Life and Works of Rizal', '2026-11-03 00:44:09'),
(35, 'FIL101', 'Wika at Kultura sa Mapayapang Lipunan', '2026-11-03 00:44:09'),
(36, 'IT152', 'Advanced Databases', '2026-11-03 00:44:09'),
(37, 'IT108', 'Requirements Engineering', '2026-11-03 00:44:09'),
(38, 'IT181', 'Application Development and Emerging Technologies', '2026-11-03 00:44:09'),
(39, 'IT191', 'Capstone Project 1', '2026-11-03 00:44:09'),
(40, 'IT198', 'Research Methods', '2026-11-03 00:44:09'),
(41, 'FIL102', 'Ekokritisismo at Pagpapahalaga sa Kalikasan', '2026-11-03 00:44:09'),
(42, 'IT153', 'Introduction to Artificial Intelligence and Expert Systems', '2026-11-03 00:44:09'),
(43, 'IT182', 'System Integration, Administration and Maintenance', '2026-11-03 00:44:09'),
(44, 'IT193', 'Special Topics in IT', '2026-11-03 00:44:09'),
(45, 'IT192', 'Capstone Project 2', '2026-11-03 00:44:09'),
(46, 'IT199', 'Undergraduate Thesis', '2026-11-03 00:44:09'),
(47, 'ENT101', 'Startup Essentials: Fundamentals of Innovation-Driven Entrepreneurship', '2026-11-03 00:44:09'),
(48, 'IT183', 'Web Systems & Technologies', '2026-11-03 00:44:09'),
(49, 'IT184', 'Social, Legal and Professional Issues in Computing', '2026-11-03 00:44:09'),
(50, 'IT185', 'Information Assurance and Security', '2026-11-03 00:44:09'),
(51, 'IT197', 'On-the-Job Training (486 Hours)', '2026-11-03 00:44:09'),
(52, 'ITE100', 'Object-Oriented Programming', '2026-11-03 00:44:09'),
(53, 'ITE101', 'Web Programming', '2026-11-03 00:44:09'),
(54, 'ITE102', 'Mobile Application Development', '2026-11-03 00:44:09'),
(55, 'ITE103', 'Software Engineering', '2026-11-03 00:44:09'),
(56, 'ITE104', 'Internet Technologies', '2026-11-03 00:44:09'),
(57, 'ITE105', 'Cyber Security Principles', '2026-11-03 00:44:09'),
(58, 'ITE106', 'Cloud Computing', '2026-11-03 00:44:09'),
(59, 'ITE107', 'Introduction to Ethical Hacking', '2026-11-03 00:44:09'),
(60, 'ITE108', 'Platform Technologies', '2026-11-03 00:44:09'),
(61, 'ITE109', 'Multimedia Systems', '2026-11-03 00:44:09'),
(62, 'ITE110', 'Computer Graphics', '2026-11-03 00:44:09'),
(63, 'ITE111', '3D Modeling and Animation', '2026-11-03 00:44:09'),
(64, 'Lang Elect', 'Foreign Language', '2026-11-03 00:44:09');

-- --------------------------------------------------------

--
-- Table structure for table `subject_prerequisites`
--

CREATE TABLE `subject_prerequisites` (
  `ID` int(11) NOT NULL,
  `SubjectID` int(11) NOT NULL,
  `PreRequisiteSubjectID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `subject_prerequisites`
--

INSERT INTO `subject_prerequisites` (`ID`, `SubjectID`, `PreRequisiteSubjectID`) VALUES
(90, 17, 6),
(89, 18, 7),
(102, 19, 8),
(101, 21, 9),
(105, 25, 14),
(104, 26, 15),
(103, 28, 16),
(93, 29, 8),
(92, 32, 3),
(91, 32, 6),
(110, 33, 22),
(107, 34, 15),
(108, 35, 24),
(109, 37, 23),
(106, 38, 25),
(94, 39, 8),
(112, 42, 31),
(111, 43, 31),
(113, 44, 31),
(114, 45, 30),
(115, 45, 31),
(117, 50, 23),
(118, 51, 31),
(119, 52, 36),
(120, 53, 39),
(121, 53, 40),
(124, 57, 38),
(125, 58, 19),
(126, 59, 50),
(129, 62, 39),
(130, 62, 40),
(116, 67, 31),
(122, 68, 31),
(123, 69, 31),
(127, 70, 31),
(128, 71, 31);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `ID` int(11) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `Username` varchar(50) DEFAULT NULL,
  `Password` varchar(255) NOT NULL,
  `RoleID` int(11) NOT NULL,
  `IsVerified` tinyint(1) DEFAULT 0,
  `Status` enum('Active','Suspended','Locked') DEFAULT 'Active',
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`ID`, `Email`, `Username`, `Password`, `RoleID`, `IsVerified`, `Status`, `CreatedAt`, `UpdatedAt`) VALUES
(1, 'dkboygrar@gmail.com', 'dkboystudios', '$2y$10$gqNBMfRrtITH3fkiJynNbOGLrFCTlRy1KS2PMHM8XvPKdznyAw4qC', 1, 1, 'Active', '2026-10-29 02:20:16', '2026-11-05 09:09:34'),
(2, 'amro@gmail.com', 'amro', '$2y$10$4QfbHfChUuYHJoPJPVXr8e1jVEgNm5HfDmYu9M4gk/Z0ZgKABeRJy', 2, 0, 'Active', '2026-10-29 17:17:04', '2026-11-03 00:43:04'),
(3, 'amrollah.dianalan@amsc.edu.ph', 'adianalan', '$2y$10$FV3NzFH2R6xfknzTi.erGOD9k4HxM8cPNam7rDVemh5q3uzCvAUvK', 1, 1, 'Active', '2026-11-01 09:22:19', '2026-11-03 00:43:04'),
(4, 'dkboystudios@gmail.com', 'dkboyrocks007', '$2y$10$VC.Gu61MZbch8FSn5D2wN.Ifje/Gfqbr/g2eiV73/oOQjPMWRNj6S', 2, 1, 'Active', '2026-11-02 23:28:39', '2026-11-05 04:24:54');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `applicants`
--
ALTER TABLE `applicants`
  ADD PRIMARY KEY (`ApplicantID`),
  ADD UNIQUE KEY `UserID` (`UserID`),
  ADD UNIQUE KEY `ApplicationNumber` (`ApplicationNumber`),
  ADD KEY `PreferredProgramID_1` (`PreferredProgramID_1`),
  ADD KEY `PreferredProgramID_2` (`PreferredProgramID_2`),
  ADD KEY `PreferredProgramID_3` (`PreferredProgramID_3`);

--
-- Indexes for table `applicant_documents`
--
ALTER TABLE `applicant_documents`
  ADD PRIMARY KEY (`DocumentID`),
  ADD KEY `ApplicantID` (`ApplicantID`),
  ADD KEY `VerifiedBy` (`VerifiedBy`);

--
-- Indexes for table `block_section_students`
--
ALTER TABLE `block_section_students`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `SectionID` (`SectionID`),
  ADD KEY `StudentID` (`StudentID`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `CurriculumSubjectID` (`CurriculumSubjectID`),
  ADD KEY `InstructorID` (`InstructorID`),
  ADD KEY `AcademicYearID` (`AcademicYearID`);

--
-- Indexes for table `class_schedules`
--
ALTER TABLE `class_schedules`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `ClassID` (`ClassID`);

--
-- Indexes for table `curriculum`
--
ALTER TABLE `curriculum`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `ProgramID` (`ProgramID`);

--
-- Indexes for table `curriculum_subjects`
--
ALTER TABLE `curriculum_subjects`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `UniqueCurriculumSubject` (`CurriculumID`,`SubjectID`),
  ADD KEY `SubjectID` (`SubjectID`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `StudentID` (`StudentID`),
  ADD KEY `ClassID` (`ClassID`);

--
-- Indexes for table `fee_assessments`
--
ALTER TABLE `fee_assessments`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `StudentID` (`StudentID`),
  ADD KEY `AcademicYearID` (`AcademicYearID`);

--
-- Indexes for table `offices`
--
ALTER TABLE `offices`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `OfficeCode` (`OfficeCode`),
  ADD KEY `OfficeHead` (`OfficeHead`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `ORNumber` (`ORNumber`),
  ADD KEY `AssessmentID` (`AssessmentID`),
  ADD KEY `VerifiedByStaffID` (`VerifiedByStaffID`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `ProgramCode` (`ProgramCode`),
  ADD KEY `CollegeID` (`CollegeID`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `RoleName` (`RoleName`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `AcademicYearID` (`AcademicYearID`);

--
-- Indexes for table `staff_designations`
--
ALTER TABLE `staff_designations`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `StaffID` (`StaffID`),
  ADD KEY `OfficeID` (`OfficeID`);

--
-- Indexes for table `staff_members`
--
ALTER TABLE `staff_members`
  ADD PRIMARY KEY (`StaffID`),
  ADD UNIQUE KEY `EmployeeNumber` (`EmployeeNumber`),
  ADD UNIQUE KEY `UserID` (`UserID`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`StudentID`),
  ADD UNIQUE KEY `ApplicantID` (`ApplicantID`),
  ADD UNIQUE KEY `StudentNumber` (`StudentNumber`),
  ADD KEY `ProgramID` (`ProgramID`),
  ADD KEY `CurriculumID` (`CurriculumID`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `SubjectCode` (`SubjectCode`);

--
-- Indexes for table `subject_prerequisites`
--
ALTER TABLE `subject_prerequisites`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `UniqueSubjectPreReq` (`SubjectID`,`PreRequisiteSubjectID`),
  ADD KEY `PreRequisiteSubjectID` (`PreRequisiteSubjectID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `Email` (`Email`),
  ADD UNIQUE KEY `Username` (`Username`),
  ADD KEY `RoleID` (`RoleID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=893;

--
-- AUTO_INCREMENT for table `applicants`
--
ALTER TABLE `applicants`
  MODIFY `ApplicantID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `applicant_documents`
--
ALTER TABLE `applicant_documents`
  MODIFY `DocumentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `block_section_students`
--
ALTER TABLE `block_section_students`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `class_schedules`
--
ALTER TABLE `class_schedules`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `curriculum`
--
ALTER TABLE `curriculum`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `curriculum_subjects`
--
ALTER TABLE `curriculum_subjects`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `fee_assessments`
--
ALTER TABLE `fee_assessments`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offices`
--
ALTER TABLE `offices`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `staff_designations`
--
ALTER TABLE `staff_designations`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `staff_members`
--
ALTER TABLE `staff_members`
  MODIFY `StaffID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `StudentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `subject_prerequisites`
--
ALTER TABLE `subject_prerequisites`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=131;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `applicants`
--
ALTER TABLE `applicants`
  ADD CONSTRAINT `applicants_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `applicants_ibfk_2` FOREIGN KEY (`PreferredProgramID_1`) REFERENCES `programs` (`ID`),
  ADD CONSTRAINT `applicants_ibfk_3` FOREIGN KEY (`PreferredProgramID_2`) REFERENCES `programs` (`ID`),
  ADD CONSTRAINT `applicants_ibfk_4` FOREIGN KEY (`PreferredProgramID_3`) REFERENCES `programs` (`ID`);

--
-- Constraints for table `applicant_documents`
--
ALTER TABLE `applicant_documents`
  ADD CONSTRAINT `applicant_documents_ibfk_1` FOREIGN KEY (`ApplicantID`) REFERENCES `applicants` (`ApplicantID`) ON DELETE CASCADE,
  ADD CONSTRAINT `applicant_documents_ibfk_2` FOREIGN KEY (`VerifiedBy`) REFERENCES `staff_members` (`StaffID`) ON DELETE CASCADE;

--
-- Constraints for table `block_section_students`
--
ALTER TABLE `block_section_students`
  ADD CONSTRAINT `block_section_students_ibfk_1` FOREIGN KEY (`SectionID`) REFERENCES `sections` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `block_section_students_ibfk_2` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`) ON DELETE CASCADE;

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`CurriculumSubjectID`) REFERENCES `curriculum_subjects` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `classes_ibfk_2` FOREIGN KEY (`InstructorID`) REFERENCES `staff_members` (`StaffID`) ON DELETE CASCADE,
  ADD CONSTRAINT `classes_ibfk_3` FOREIGN KEY (`AcademicYearID`) REFERENCES `academic_years` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `class_schedules`
--
ALTER TABLE `class_schedules`
  ADD CONSTRAINT `class_schedules_ibfk_1` FOREIGN KEY (`ClassID`) REFERENCES `classes` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `curriculum`
--
ALTER TABLE `curriculum`
  ADD CONSTRAINT `curriculum_ibfk_1` FOREIGN KEY (`ProgramID`) REFERENCES `programs` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `curriculum_subjects`
--
ALTER TABLE `curriculum_subjects`
  ADD CONSTRAINT `curriculum_subjects_ibfk_1` FOREIGN KEY (`CurriculumID`) REFERENCES `curriculum` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `curriculum_subjects_ibfk_2` FOREIGN KEY (`SubjectID`) REFERENCES `subjects` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`ClassID`) REFERENCES `classes` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `fee_assessments`
--
ALTER TABLE `fee_assessments`
  ADD CONSTRAINT `fee_assessments_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fee_assessments_ibfk_2` FOREIGN KEY (`AcademicYearID`) REFERENCES `academic_years` (`ID`);

--
-- Constraints for table `offices`
--
ALTER TABLE `offices`
  ADD CONSTRAINT `offices_ibfk_1` FOREIGN KEY (`OfficeHead`) REFERENCES `staff_members` (`StaffID`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`AssessmentID`) REFERENCES `fee_assessments` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`VerifiedByStaffID`) REFERENCES `staff_members` (`StaffID`) ON DELETE SET NULL;

--
-- Constraints for table `programs`
--
ALTER TABLE `programs`
  ADD CONSTRAINT `programs_ibfk_1` FOREIGN KEY (`CollegeID`) REFERENCES `offices` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`AcademicYearID`) REFERENCES `academic_years` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `staff_designations`
--
ALTER TABLE `staff_designations`
  ADD CONSTRAINT `staff_designations_ibfk_1` FOREIGN KEY (`StaffID`) REFERENCES `staff_members` (`StaffID`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_designations_ibfk_3` FOREIGN KEY (`OfficeID`) REFERENCES `offices` (`ID`) ON DELETE SET NULL;

--
-- Constraints for table `staff_members`
--
ALTER TABLE `staff_members`
  ADD CONSTRAINT `staff_members_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`ID`) ON DELETE SET NULL;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`ApplicantID`) REFERENCES `applicants` (`ApplicantID`),
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`ProgramID`) REFERENCES `programs` (`ID`),
  ADD CONSTRAINT `students_ibfk_3` FOREIGN KEY (`CurriculumID`) REFERENCES `curriculum` (`ID`);

--
-- Constraints for table `subject_prerequisites`
--
ALTER TABLE `subject_prerequisites`
  ADD CONSTRAINT `subject_prerequisites_ibfk_2` FOREIGN KEY (`PreRequisiteSubjectID`) REFERENCES `subjects` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `subject_prerequisites_ibfk_3` FOREIGN KEY (`SubjectID`) REFERENCES `curriculum_subjects` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`RoleID`) REFERENCES `roles` (`ID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
