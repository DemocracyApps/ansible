##  TTP Ansible Repository
Ansible scripts for TTP infrastructure.

## Documentation

Documentation is maintained together with the code in this repository.
* [Introduction](documentation/readme.md)
* [Naming Conventions](documentation/naming.md)
* Something else.

Note the following for getting git push to work from inside the TTP directory (working on Vagrant):

  > eval "$(ssh-agent -s)"
  > ssh-add -l
  > ssh-add ../githubkey.pvt # or wherever it is.
  > ssh-add -l
  > git push origin master


