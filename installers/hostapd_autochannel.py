from bestchannel import find_best_channel
import re, sys
def set_hostapd_channel(channel, configfile):
    new_config = ""
    reChannel = r'^channel=\d+'
    with open(configfile, "r") as roconfig:
        for line in roconfig:
            if re.match(reChannel, line):
                new_config += "channel="+str(channel)+"\n"
            else:
                new_config += line
    with open(configfile, "w") as writeconfig:
        writeconfig.write(new_config)

if __name__ == "__main__":
    if len(sys.argv) < 4:
        print("usage : " + sys.argv[0] + " [interface] [max_rssi] [max_bssid_per_chan]")
        sys.exit(1)
    interface = sys.argv[1]
    max_rssi = float(sys.argv[2])
    max_bssid_per_chan = int(sys.argv[3])

    best_channel = find_best_channel(interface, max_rssi, max_bssid_per_chan)
    print("Setting", best_channel, "as the hostapd channel")
    set_hostapd_channel(best_channel, "/etc/hostapd/hostapd.conf")
