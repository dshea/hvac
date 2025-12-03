This is a monitor for my Geothermal 3 stage heating and AC system.

DHT11 temperature and humidity sensor on pin GPIO 5
telephone wire between DHT11 and RPi
	  red = +3.3v
	  black = ground
	  yellow = signal GPIO 5

HVAC system
ethernet cable between HVAC and rectifier board.  24VAC signal
	 common (C) = white/orange = common for all channels
	 stage 1 (Y1) = orange = channel 1 = GPIO 9
	 stage 2 (Y2) = brown = channel 2 = GPIO 10
	 stage 3 - electric coils (W) = green = channel 3 = GPIO 11


To get the program to run at boot:

copy the hvac.service file to /etc/systemd/system
set permissions to 644
sudo systemctl enable hvac.service


DEVELOPENT

- to start a web server to test out the PHP

cd web
php -S localhost:8000

web page used to upload file
http://localhost:8000/uploadJson.php

http://localhost:8000/uploadTest.php


python command to upload file

./sendJson.py -f junk.json -u http://localhost:8000/uploadTest.php --auth don:stuff

