# Changes

## 0.6.0 (2026100800)

- **Recent classes**: a new section with the five latest classes, newest first. Each row shows
  the class (linked to its report), its course and start time, and how many of the expected
  students were present overall ("17 of 18 present"). The percentage is coloured by the Present
  and Partial thresholds. Managers see every course's classes, teachers and coordinators their
  own courses' (only their groups in separate-groups courses).
- *My students* now shows only the low-student count per course: the latest class's headcount
  moved to *Recent classes*.
- The period setting is now *Period (days)*, as it also sets how far back *Recent classes* goes.

## 0.5.1 (2026100604)

- *Teacher attendance* uses the site's role names, as set under *Define roles* (or in a language
  customisation): the hint names the listed role ("Role: Teacher.") instead of saying
  "Non-editing teachers".
- Its link, which opens the overview of every teacher including coordinators, now says so with
  the role names ("Every Teacher and Coordinator ›"; for coordinators "My teaching and every
  Teacher ›") instead of "All non-editing teachers (8)".

## 0.5.0 (2026100603)

- The block is rebuilt as soon as Zoom attendance's data changes (a sync, an exclusion, an
  enrolment), not only after an hour.
- **Moodle app**: the block shows on the app's dashboard with the same sections.
- Maturity is now beta. CI also runs Moodle 4.1 on PHP 7.4.
- Needs local_zoomattendance 0.4.0.

## 0.4.3

Accurate refresh and *My students* hints.

## 0.4.2

*Teacher attendance* lists non-editing teachers only, for managers and coordinators.

## 0.4.1

Latest class headcount counts present and partial together as present overall.

## 0.4.0

Latest class headcount under each course in *My students*.

## 0.3.0 and earlier

Dashboard sections for students, teachers, coordinators and managers, with bars coloured as in
Zoom attendance and "When joined" for teachers.
