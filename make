#!/bin/sh
rm package/packages/com_ipvenger.zip
rm package/packages/plg_ipvenger.zip
rm ipvenger.zip
zip -r package/packages/com_ipvenger.zip com_ipvenger
zip -r package/packages/plg_ipvenger.zip plg_ipvenger
zip -r ipvenger.zip package
