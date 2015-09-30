#!/bin/sh
sudo rm /tmp/obfdev_cert.crt /usr/local/share/ca-certificates/obfdev_cert.crt
sudo update-ca-certificates --fresh
