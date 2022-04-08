# AWS Build Scripts
The point of these scripts are to build the assets required, and to deploy them.



1. The site starts with its code in AWS CodeCommit.
2. When there is a code push to certain branches (e.g. develop, stage, prod), a build/deploy kicks off.
   1. AWS CodeBuild
   2. AWS CodeDeploy
