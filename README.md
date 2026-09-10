# Study Coach Exam Plugin (WCE)

A custom WordPress online examination management plugin designed for educational platforms, schools, universities, and organizations.

## Overview

Study Coach Exam Plugin (WCE) is a custom-built WordPress examination management system that provides tools for creating question banks, designing online exams, managing candidates' attempts, automatically grading multiple-choice questions, manually grading descriptive questions, and reviewing examination results.

The plugin also includes configurable exam timing, randomized question selection, answer-sheet management, CSV question import/export functionality, printable answer sheets, and examination event logging.

## Features

### Question Management

- Create and manage question banks
- Multiple-choice questions
- Descriptive questions
- Question editing and deletion
- CSV question import
- Sample CSV template generation

### Exam Management

- Create and edit online exams
- Select a question bank for each exam
- Configurable exam duration
- Configurable passing percentage
- Random selection of multiple-choice questions
- Random selection of descriptive questions
- Start and end date/time
- Active/inactive exam status
- Configurable unanswered-question policy

### Examination System

- Dedicated exam URLs
- Countdown timer
- Online answer saving
- Multiple-choice answer handling
- Descriptive answer handling
- Automatic exam submission when the timer expires
- Examination answer sheet

### Grading & Results

- Automatic multiple-choice grading
- Descriptive-question manual grading
- Final score calculation
- Correct / incorrect / unanswered statistics
- Detailed candidate answer sheets
- Bulk result management
- CSV result export
- Printable answer sheets
- PDF-ready print output

### Monitoring & Security

- WordPress nonce verification
- Administrator capability checks
- Input sanitization
- Secure database queries using WordPress database APIs
- Examination event logging
- Browser tab visibility monitoring during exams

## Database Architecture

The plugin creates dedicated database tables for:

| Table | Purpose |
|---|---|
| `wce_question_banks` | Question bank management |
| `wce_questions` | Question storage |
| `wce_exams` | Exam configuration |
| `wce_attempts` | Candidate examination attempts |
| `wce_answers` | Candidate answers |
| `wce_logs` | Examination event logs |

## Admin Dashboard

The WordPress administration panel provides dedicated sections for:

- Dashboard & Guide
- Question Banks
- Questions
- Exams
- Results & Grading

## Installation

1. Download the plugin.
2. Upload the plugin file to:

   `wp-content/plugins/`

3. Go to:

   `WordPress Dashboard → Plugins`

4. Activate **Study Coach Exam Plugin (WCE)**.

5. The plugin automatically initializes the required database tables.

## Creating an Exam

The general workflow is:

```text
Create Question Bank
        ↓
Add / Import Questions
        ↓
Create Exam
        ↓
Configure Exam Settings
        ↓
Publish Exam
        ↓
Candidates Take Exam
        ↓
Automatic / Manual Grading
        ↓
Review Results
