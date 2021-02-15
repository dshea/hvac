#!/usr/bin/env python3

from gpiozero import Button
from signal import pause
from time import time, ctime, sleep
import board
import busio
import adafruit_am2320

from hvac_database import writeHvac

stage1Pin = 17
stage2Pin = 22
stage3Pin = 27


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


def run():
    hvac = Hvac()
    stage1 = HvacStage(hvac, 1, stage1Pin)
    stage2 = HvacStage(hvac, 2, stage2Pin)
    stage3 = HvacStage(hvac, 3, stage3Pin)

# pause loops for ever
#    pause()
    tick = 0
    while(True):
        sleep(5)
        #print("tick", tick)
        tick += 1


if __name__ == '__main__':
    run()
