# Changes

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
