##  Ansible & Vagrant Scripts for Server Management

Set the variables in run.yml, then

vagrant up
vagrant ssh
cd /var/www
ansible-playbook -i "localhost," -c local playbooks/configureDevServer.yml

Note that this creates a webserver as well which assumes that the app (done separately) will be installed in /var/www/{app_name} and that a web site corresponding to it will be created.

May want to break out the web site creation as a separate playbook.
