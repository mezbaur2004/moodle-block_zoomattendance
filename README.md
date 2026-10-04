# Zoom attendance block (block_zoomattendance)

A Moodle dashboard block for [local_zoomattendance](https://github.com/mezbaur2004/moodle-local_zoomattendance).
It shows each user their Zoom attendance at a glance, as far as their role allows.

## Requirements

- Moodle 4.1 or later
- local_zoomattendance 0.3.4 (`2026100401`) or later

## Installation

Install into `blocks/zoomattendance` and complete the upgrade from
*Site administration > Notifications*. Then add the block to the Dashboard: either per user
(*Customise this page*), or for everyone under *Site administration > Appearance > Default
Dashboard page* followed by *Reset Dashboard for all users*. It can also go on the site front page.

## What it shows

Each section appears only for users it applies to, and every figure comes from
local_zoomattendance, so the block always agrees with its reports.

| Section | Shown to | Content |
|---|---|---|
| My attendance | Students | Course overall % per course, coloured by threshold, linked to their attendance page |
| My students | Teachers, Coordinators | Per course: how many students are low (below the Partial threshold), linked to the course report. In separate-groups courses, a user without *Access all groups* counts only their own groups |
| My teaching | Teachers, Coordinators | Their own teaching attendance per course (teacher tracking on) |
| Teacher attendance | Managers | The five teachers with the lowest attendance, and a link to the all-courses report (teacher tracking on) |

Each percentage has a thin bar coloured as Zoom attendance colours it: green from the Present
threshold, orange from the Partial threshold and red below, with a line at the Present threshold.
A red value is also labelled *Low*, so it never relies on colour alone. Student figures use the
site defaults of local_zoomattendance (75 % / 50 % by default); teacher figures use its teacher
thresholds (90 % / 10 % by default). Students below the Partial threshold are counted as low.
Teacher rows also show *When joined*: attendance over only the classes the teacher joined, as in
local_zoomattendance. Users with nothing to see do not see the block.

## Settings

*Site administration > Plugins > Blocks > Zoom attendance*:

| Setting | Default | Meaning |
|---|---|---|
| Teacher period | 30 days | Period the teacher sections cover |

The colours and the low threshold come from local_zoomattendance's own thresholds, so the block
and the reports always agree.

## Performance

Attendance changes only when local_zoomattendance's hourly sync runs, so each user's block
content is cached for up to an hour. The first dashboard view after that builds it again.
The student sections cover the user's enrolled courses only.

## Privacy

The block stores no personal data.

## License

GNU GPL v3 or later. Copyright 2026 Mezbaur Are Rafi.
