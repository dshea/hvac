#!/usr/bin/env python3

from gpiozero import Button
from signal import pause
from time import time, ctime, sleep
import board
import busio
import adafruit_am2320
import os.path

from hvacDatabase import writeHvac, makeJson
from sendJson import uploadJson, buildAuth

stage1Pin = 17
stage2Pin = 22
stage3Pin = 27

# file that stores the last time a JSON file was sent
timeFileName = "lastJsonWrite.txt"

# read from JSON config file
uploadUser = ""
uploadPassword = ""
uploadUrl = ""
uploadDelay = 3600
uploadFilename = ""

class Hvac():
    """A class to track the HVAC cycling"""

    def __init__(self):
        self.stage = 0
        self.temperature = -100.0
        self.humidity = -100.0
        self.i2c = busio.I2C(board.SCL, board.SDA)
        self.am2320 = adafruit_am2320.AM2320(self.i2c)

    def setStage(self, val):
        self.stage = val
        self.readTemperature()
        writeHvac(self.stage, self.temperature, self.humidity)
        print(ctime(time()), "Stage =", self.stage, "Temp =", self.temperature, "Humidity =", self.humidity)

    def readTemperature(self):
        self.temperature = (self.am2320.temperature * 9.0 / 5.0) + 32.0
        self.humidity = self.am2320.relative_humidity


class HvacStage(Button):
    """A class to trigger a single stage HVAC cycle"""

    def __init__(self, hvac, stageNum, pin):
        super().__init__(pin, pull_up=False, active_state=None)
        self.hvac = hvac
        self.stageNum = stageNum
        self.when_pressed = self.stageOn
        self.when_released = self.stageOff

    def stageOn(self):
        self.hvac.setStage(self.stageNum)

    def stageOff(self):
        self.hvac.setStage(self.stageNum - 1)

def readConfigFile():
    global uploadUser, uploadPassword, uploadUrl, uploadDelay, uploadFilename
    import json
    configFileName = "config.json"
    if os.path.exists(configFileName):
        with open(configFileName, 'r') as f:
            config = json.load(f)
            uploadUser = config.get("upload_user", "")
            uploadPassword = config.get("upload_password", "")
            uploadUrl = config.get("upload_url", "")
            uploadDelay = config.get("upload_delay", 3600)
            uploadFilename = config.get("upload_filename", "hvac_upload.json")

def writeTimeFile(t):
    with open(timeFileName, 'w') as f:
        f.write(str(t))

def readTimeFile():
    timeVal = 0
    if os.path.exists(timeFileName):
        with open(timeFileName, 'r') as f:
            val = f.read()
            timeVal = float(val)
    return timeVal

def sendJson(jsonStr):

    # write JSON string to file
    with open(uploadFilename, 'w') as file:
        file.write(jsonStr)

    auth = buildAuth(f"{uploadUser}:{uploadPassword}")
    try:
        resp = uploadJson(uploadFilename, uploadUrl, method="post", auth=auth, retries=3, timeout=30)
    except Exception as e:
        return False
    return True

def run():
    readConfigFile()

    hvac = Hvac()
    stage1 = HvacStage(hvac, 1, stage1Pin)
    stage2 = HvacStage(hvac, 2, stage2Pin)
    stage3 = HvacStage(hvac, 3, stage3Pin)

    # last time JSON sent to server
    lastSendTime = readTimeFile()

    # pause loops forever
    #    pause()
    tick = 0
    while(True):
        sleep(5)
        #print("tick", tick)
        tick += 1

        t1 = time()
        if(t1 - lastSendTime > uploadDelay):
            jsonStr = makeJson(lastSendTime)
            if(sendJson(jsonStr)):
                lastSendTime = t1
                writeTimeFile(lastSendTime)

if __name__ == '__main__':
    run()
