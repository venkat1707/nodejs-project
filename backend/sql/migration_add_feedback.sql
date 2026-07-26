-- Migration: add the feedback table used by the "Share Feedback" page.
-- Stores user suggestions about improvements and desired new features.
USE sales_db;

CREATE TABLE IF NOT EXISTS feedback (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NULL,
  email VARCHAR(255) NULL,
  rating TINYINT NULL,
  improvements TEXT NULL,
  new_features TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_feedback_created
  ON feedback (created_at);
