#!/bin/sh
echo "" | openssl s_client -connect obfdev.discendum.com:443 -prexit 2>/dev/null | sed -n -e '/BEGIN\ CERTIFICATE/,/END\ CERTIFICATE/ p' > /tmp/obfdev_cert.crt
sudo cp /tmp/obfdev_cert.crt /usr/local/share/ca-certificates/
rm /tmp/obfdev_cert.crt
sudo update-ca-certificates
