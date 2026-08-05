#!/bin/sh
set -eu

echo "SmartFactory NGINX: validating configuration after Docker DNS initialization."
nginx -t