#!/bin/bash

for A in /usr/share/issabel/storage/mount*
do
DEVICE=`cat $A`
if [ "x$DEVICE" != "x" ]; then
ERROR=$(/usr/share/issabel/privileged/storage -d $DEVICE -a mount 2>&1 >/dev/null)
if [ $? -eq 1 ]; then
    echo "$ERROR" >/usr/share/issabel/storage/error
    chown asterisk.asterisk /usr/share/issabel/storage/error
else
    rm -f /usr/share/issabel/storage/error
    rm -f $A
fi
fi
done

for A in /usr/share/issabel/storage/umount*
do
DEVICE=`cat $A`
if [ "x$DEVICE" != "x" ]; then
ERROR=$(/usr/share/issabel/privileged/storage -d $DEVICE -a umount 2>&1 >/dev/null)
if [ $? -eq 1 ]; then
    echo "$ERROR" >/usr/share/issabel/storage/error
    chown asterisk.asterisk /usr/share/issabel/storage/error
else
    rm -f /usr/share/issabel/storage/error
    rm -f $A
fi
fi
done
