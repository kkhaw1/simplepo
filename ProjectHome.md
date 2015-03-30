# SimplePO #

We grew tired of emailing PO files to translators, explaining how to edit the files, and waiting until they returned the translation file.  We created Simple PO so we could just ask our translators to go to a web page and start translating.

Simple Po is designed to do three things:
  1. Provide an easy to use front end that translators can use without having to understand the PO format or install anything.
  1. Import PO files into a MySQL database
  1. Export the data back to a po file.

Simple PO is not:
  1. A complex cataloge management system
  1. Translator management system
  1. A replacement for any of the gettext tools.
  1. A verion control system for translations.

## Requirements ##
Using Simple PO requires php, including command line interface, and mysql.
It is also important to have the gettext family of tool installed on the server.

## Known Issues ##
Simple PO doesn't handle plurals.

## Here are a few screenshots ##
### Catalogue Index ###
<img src='http://kamran.yabla.com/simplepo/Screenshots/SimplePO_1.png' alt='SimplePO Home' width='400' height='300' /><br />
### Tranlation Page ###
<img src='http://kamran.yabla.com/simplepo/Screenshots/SimplePO_2.png' alt='SimplePO' width='400' height='300' /><br />
