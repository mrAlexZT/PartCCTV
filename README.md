PartCCTV, Yet Another CCTV App
==================

By [@mironoff111](https://github.com/mironoff111)

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/6308734b-20af-4963-b73e-a1c860cfb595/mini.png)](https://insight.sensiolabs.com/projects/6308734b-20af-4963-b73e-a1c860cfb595)

## Features
  - Lightweight
  - Open Source
  - Made with love :)  
    
### Requirements
  - `Linux`/`FreeBSD`/`MacOSX` ( except `Windows` because of `pcntl_fork()` )
  - `PHP 7.0` `CLI` and `FPM` with `PDO`  
  - `ZeroMQ` and PHP `ZeroMQ binding` ( http://zeromq.org/bindings:php )
  
  for Ubuntu:
  ```
  sudo apt install php7.0-dev libzmq-dev pkg-config php-pear
  sudo pecl install zmq-beta
  echo "extension=zmq.so" >> /etc/php/7.0/mods-available/zmq.ini
  ln -s /etc/php/7.0/mods-available/zmq.ini /etc/php/7.0/fpm/conf.d/20-zmq.ini
  ln -s /etc/php/7.0/mods-available/zmq.ini /etc/php/7.0/cli/conf.d/20-zmq.ini
  ``` 
  
  - `PDO` compatible DB (MySQL, Postgresql, SQlite, etc.)
  - `FFmpeg` 
  
  for Ubuntu:
  ```
  sudo add-apt-repository -y ppa:djcj/hybrid
  sudo apt install ffmpeg
  ```
    
## Installation
  - Clone it: `git clone https://github.com/mironoff111/PartCCTV`
  
  `cd PartCCTV`
  - Install all dependencies: `php composer.phar install`
  - Configure `nginx` (using `install/nginx.conf` as example) or configure `Apache` (no example config: TBD)
  - Restore DB from .sql file (using `install/mysql.sql`, `install/postgre.sql` or converting it to another DB)
  - Configure and rename `PartCCTV.ini.example` to `PartCCTV.ini` file
  - Edit and move `install/partcctv.service` to `/etc/systemd/system/partcctv.service` (or run the platform manually)
  
  ```
  systemctl enable partcctv
  systemctl start partcctv
  ```
  - Set-up core with `web_gui` or `API`
  - That's all :)
  

## About

![Block-scheme](https://raw.githubusercontent.com/mironoff111/PartCCTV/gh-pages/1111.png)

### Contributing
  - Fork it: https://github.com/mironoff111/PartCCTV/fork
  - Create your feature branch: `git checkout -b my-new-feature`
  - Commit your changes: `git commit -am 'Add some feature'`
  - Push to the branch: `git push origin my-new-feature`
  - Create a new Pull Request

### License

PartCCTV is licensed under the CC BY-NC-SA 4.0 License - see the `LICENSE.md` file for details
