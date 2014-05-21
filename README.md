# Move Scrapbook To Stack Job

A concrete5 job for migration from legacy Scrapbooks to Stacks

## Usage

* upload this php file to jobs directory
* install the job in dashboard > System & Settings > Optimization > Automated Jobs
* run
* after run, uninstall the job

## Note

Block->move() method is buggy in pre-5.6.x. please run this job after upgrade to 5.6.x or later.

See: https://github.com/concrete5/concrete5/commit/4ea06d7fc40eb24c567f5d19c9e3c3648c096e72

## Original code

This code is originally posted on concrete5.org forum via skote
http://www.concrete5.org/community/forums/usage/is-there-a-way-to-convert-to-scrapbook-to-stack/
