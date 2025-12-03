#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Created on Mon Feb 10 00:25:16 2021

@author: Don Shea
"""
import os
import time
import sqlite3
import json
import sys

# database stuff
db_filename = "hvac.db"

def openDatabase():
    conn = None
    if not os.path.isfile(db_filename):
        conn = sqlite3.connect(db_filename)
        c = conn.cursor()

        # Create table hvac
        c.execute('''CREATE TABLE hvac
                 (time INT, stage INT, temperature REAL, humidity REAL)''')
        
        conn.commit()
    else:
        conn = sqlite3.connect(db_filename)
        c = conn.cursor()
    return conn, c

def writeHvac(stage, temperature, humidity):
    conn, c = openDatabase()

    # Insert a new row
    c.execute("INSERT INTO hvac VALUES (?,?,?,?)", 
              (int(time.time()), stage, temperature, humidity))

     # Save (commit) the changes
    conn.commit()
    conn.close()

def makeJson(time) :
    conn, c = openDatabase()
    query =  c.execute('SELECT * FROM hvac  WHERE time > ? ORDER BY time', (time,))
    rows = query.fetchall()
    conn.close()
    return json.dumps(rows, separators=(',', ':'))

def dumpDatabase() :
    conn, c = openDatabase()
    
    print("hvac database")
    print("time", "stage", "temperature", "humidity")
    print("----", "-----", "-----------", "--------")
    for row in c.execute('SELECT * FROM hvac ORDER BY time'):
        print(row)
    conn.close()

if __name__ == '__main__':
    if len(sys.argv) == 2 :
        db_filename = sys.argv[1]

#    print(makeJson(1613064675))
    dumpDatabase()

    
