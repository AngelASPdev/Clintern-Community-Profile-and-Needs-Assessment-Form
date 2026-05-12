-- ════════════════════════════════════════════════════════════════════════════════
-- DATABASE MIGRATION: Community Profile & Needs Assessment Module
-- WMSU-OESCD System
-- ════════════════════════════════════════════════════════════════════════════════

-- ────────────────────────────────────────────────────────────────────────────────
-- 1. EXPAND COMMUNITY_PROFILES TABLE (Step 1: Respondent Profile)
-- ────────────────────────────────────────────────────────────────────────────────

ALTER TABLE `community_profiles` 
ADD COLUMN `step_completed` INT DEFAULT 0 COMMENT 'Tracks which step user completed (0-4)',
ADD COLUMN `submission_status` ENUM('draft', 'incomplete', 'submitted') DEFAULT 'draft',
ADD COLUMN `photo_path` VARCHAR(255) DEFAULT NULL COMMENT 'Path to student photo',
ADD COLUMN `age` INT DEFAULT NULL,
ADD COLUMN `gender` VARCHAR(20) DEFAULT NULL,
ADD COLUMN `position_in_family` VARCHAR(100) DEFAULT NULL,
ADD COLUMN `address` TEXT DEFAULT NULL,
ADD COLUMN `occupation` VARCHAR(255) DEFAULT NULL,
ADD COLUMN `ethnicity` VARCHAR(100) DEFAULT NULL,
ADD COLUMN `religion` VARCHAR(100) DEFAULT NULL,
ADD COLUMN `monthly_income` DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN `civil_status` VARCHAR(50) DEFAULT NULL,
ADD COLUMN `dialect` VARCHAR(100) DEFAULT NULL,
ADD COLUMN `highest_education` VARCHAR(255) DEFAULT NULL,
ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- ────────────────────────────────────────────────────────────────────────────────
-- 2. CREATE HOUSEHOLD_MEMBERS TABLE (Step 2: Household Profile)
-- ────────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `household_members` (
  `member_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `profile_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `date_of_birth` DATE DEFAULT NULL,
  `age` INT DEFAULT NULL,
  `gender` VARCHAR(20) DEFAULT NULL,
  `education_level` VARCHAR(255) DEFAULT NULL,
  `occupation` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`profile_id`) REFERENCES `community_profiles` (`profile_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ────────────────────────────────────────────────────────────────────────────────
-- 3. CREATE HOUSEHOLD_PROFILE TABLE (Step 2: Household Details)
-- ────────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `household_profile` (
  `household_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `profile_id` INT NOT NULL UNIQUE,
  `household_type` VARCHAR(100) DEFAULT NULL COMMENT 'Shanty, Wood, Concrete, etc.',
  `family_structure` VARCHAR(50) DEFAULT NULL COMMENT 'Nuclear or Extended',
  `land_ownership` VARCHAR(50) DEFAULT NULL COMMENT 'Yes/No',
  `length_of_stay` VARCHAR(100) DEFAULT NULL COMMENT 'Duration in years or description',
  `land_area_use` VARCHAR(255) DEFAULT NULL COMMENT 'Description of land use',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`profile_id`) REFERENCES `community_profiles` (`profile_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ────────────────────────────────────────────────────────────────────────────────
-- 4. CREATE FAMILY_HEALTH TABLE (Step 3: Family Health)
-- ────────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `family_health` (
  `health_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `profile_id` INT NOT NULL UNIQUE,
  `vaccinated` VARCHAR(50) DEFAULT NULL COMMENT 'Yes/No',
  `vaccination_details` TEXT DEFAULT NULL,
  `health_consultation` VARCHAR(50) DEFAULT NULL COMMENT 'Yes/No',
  `expert_consulted` VARCHAR(100) DEFAULT NULL COMMENT 'Doctor, Midwife, Albularyo, etc.',
  `consultation_frequency` VARCHAR(100) DEFAULT NULL COMMENT 'Frequency of consultation',
  `health_notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`profile_id`) REFERENCES `community_profiles` (`profile_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ────────────────────────────────────────────────────────────────────────────────
-- 5. CREATE WORK_EXPERIENCE TABLE (Step 4: Work & Skills)
-- ────────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `work_experience` (
  `work_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `profile_id` INT NOT NULL UNIQUE,
  `employment_status` VARCHAR(100) DEFAULT NULL COMMENT 'Public, Private, Self-employed',
  `work_type` VARCHAR(255) DEFAULT NULL COMMENT 'Government, Fisherman, etc.',
  `years_in_job` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`profile_id`) REFERENCES `community_profiles` (`profile_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ────────────────────────────────────────────────────────────────────────────────
-- 6. CREATE SKILLS_LEARNED TABLE (Step 4: Skills Checklist)
-- ────────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `skills_learned` (
  `skill_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `work_id` INT NOT NULL,
  `skill_name` VARCHAR(255) NOT NULL COMMENT 'Farming, Cookery, Plumbing, etc.',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`work_id`) REFERENCES `work_experience` (`work_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ────────────────────────────────────────────────────────────────────────────────
-- 7. CREATE UPLOADS DIRECTORY RECORD TABLE
-- ────────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `profile_uploads` (
  `upload_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `profile_id` INT NOT NULL,
  `file_type` VARCHAR(50) NOT NULL COMMENT 'photo, document, etc.',
  `file_path` VARCHAR(255) NOT NULL,
  `file_size` INT DEFAULT NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`profile_id`) REFERENCES `community_profiles` (`profile_id`) ON DELETE CASCADE,
  KEY (`profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ────────────────────────────────────────────────────────────────────────────────
-- 8. CREATE INDEXES FOR BETTER PERFORMANCE
-- ────────────────────────────────────────────────────────────────────────────────

CREATE INDEX idx_household_members_profile ON household_members(profile_id);
CREATE INDEX idx_family_health_profile ON family_health(profile_id);
CREATE INDEX idx_work_experience_profile ON work_experience(profile_id);
CREATE INDEX idx_skills_work ON skills_learned(work_id);
CREATE INDEX idx_submissions_status ON community_profiles(submission_status);
CREATE INDEX idx_submissions_step ON community_profiles(step_completed);

-- ────────────────────────────────────────────────────────────────────────────────
-- MIGRATION COMPLETE
-- ────────────────────────────────────────────────────────────────────────────────
-- Note: Run this migration on your database to prepare for the new multi-step form
-- Ensure you have backups before running this migration
