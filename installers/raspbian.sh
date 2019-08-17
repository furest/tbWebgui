UPDATE_URL="https://raw.githubusercontent.com/furest/tbWebgui/master/"
wget -q ${UPDATE_URL}/installers/common.sh -O /tmp/raspapcommon.sh
source /tmp/raspapcommon.sh && rm -f /tmp/raspapcommon.sh

function update_system_packages() {
    install_log "Updating sources"
    sudo apt update || install_error "Unable to update package list"
}

function install_dependencies() {
    install_log "Installing required packages"
    sudo apt-get install lighttpd $php_version-cgi $php_version-xml $php_version-curl git hostapd dnsmasq vnstat openjdk-8-jre-headless libpcap0.8 || install_error "Unable to install dependencies"
}

function install_additionnal_drivers() {
    install_log "Installing additionnal drivers"
    install_driver "8188eu"
}

function install_driver() {
    if [[ -n "$1" ]]; then
        install_log "Installing driver $1"
        devicemodel=$1
        kernel=$(uname -r | tr -d '+')
        build=${build:-$(uname -v | awk '{print $1}' | tr -d '#')}
        module_dir=/lib/modules/`uname -r`/kernel/drivers/net/wireless
        filename="$devicemodel-$kernel-$build.tar.gz"
        wget http://downloads.fars-robotics.net/wifi-drivers/$devicemodel-drivers/$filename
        if [ $? != 0 ]; then
                install_warning "File $filename not found. There might not be any driver for your hardware or kernel version"
                return 1
        fi
        mkdir -p $devicemodel
        tar -xvf $filename -C $devicemodel
        cd $devicemodel
        sudo mv $devicemodel.conf /etc/modprobe.d/.
        sudo install -p -m 644 $devicemodel.ko $module_dir
        sudo depmod `uname -r`
        cd ..
        rm -r $devicemodel
        rm -r $filename
    fi
  
}

install_raspap
