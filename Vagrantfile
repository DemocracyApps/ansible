# -*- mode: ruby -*-
# vi: set ft=ruby :

VAGRANTFILE_API_VERSION = "2"

Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|

  config.vm.box = "ubuntu/trusty64"

  config.vm.network "forwarded_port", guest: 80, host: 8080
  config.vm.synced_folder ".", "/var/www", :mount_options => ["dmode=777","fmode=666"]
  config.vm.network :private_network, ip: "192.168.33.25"
  config.vm.provider "virtualbox" do |v|
    v.memory = 1024
    v.cpus = 1
  end
  config.vm.provider :virtualbox do |vb, override|
    override.vm.provision :shell, :path => "bootstrap.sh", :args => ["vagrant"]
  end
#  config.vm.provision :ansible do |ansible|
#    ansible.playbook = "playbooks/initialize.yml"
#  end

end
