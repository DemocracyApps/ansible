##  Ansible & Vagrant Scripts for Server Management 

Set the variables in run.yml, then

vagrant up
vagrant ssh
cd /var/www
ansible-playbook -i "localhost," -c local playbooks/configureDevServer.yml

