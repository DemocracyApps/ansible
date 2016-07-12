#!/bin/bash
echo "Installing git, curl, python and ansible"

sudo add-apt-repository -y ppa:ansible/ansible
sudo apt-get update
sudo apt-get install -y git-core curl python-psycopg2 ansible
