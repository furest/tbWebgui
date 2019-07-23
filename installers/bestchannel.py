
import sys, subprocess, re, os
from statistics import mean
def print_array(networks_array):
    for net in networks_array:
        for k, v in net.items():
            print('%7.7s=%  6.6s' % (k, v), end='|')
        print()

def check_free_channel(networks_array):
    """
    Checks whether any channel is currently unused.
    Returns the number of the first free channel if there is any.
    returns NULL otherwise.
    """
     #Is any channel free?
    for channel in [1, 6, 11]:
        if not any(c['channel'] == channel for c in networks_array):
            #Channel  is free
            return channel
    return None

def freq2chan(freq):
    return int((freq-2412)/5+1)

def scan(interface):
    complete_scan = subprocess.Popen(["iw", "dev", interface, "scan"], stdout=subprocess.PIPE)
    out, err = complete_scan.communicate()
    out = out.decode()
    out_array = out.split('\n')

    reBSS = r'^BSS\s+(?P<BSSID>([a-fA-F0-9]{2}:?){6})'
    reSignal = r'^\s+signal:\s(?P<signal>-?\d+(.\d*)?)'
    reFreq = r'^\s+freq:\s(?P<freq>\d+)'
    reSSID = r'^\s+SSID:\s(?P<SSID>.+)$'

    curBSSID = None
    all_BSSID = []

    for line in out_array:
        if re.match(reBSS, line):
            if curBSSID != None:
                all_BSSID.append(curBSSID)
            curBSSID = dict()
            curBSSID['BSSID'] = re.match(reBSS, line).group('BSSID')
            curBSSID['SSID'] = "" #If the SSID is hidden no output is given. Better set it now than never.
        elif curBSSID != None and re.match(reSignal, line):
            match = re.match(reSignal, line).group('signal')
            curBSSID['signal'] = float(match)
        elif curBSSID != None and re.match(reFreq, line):
            match = re.match(reFreq, line).group('freq')
            curBSSID['channel'] = freq2chan(int(match))
        elif curBSSID != None and re.match(reSSID, line):
            curBSSID['SSID'] = re.match(reSSID, line).group('SSID')
    if curBSSID != None:
         all_BSSID.append(curBSSID)

    return all_BSSID
def aggregate(networks_array):
    for network in networks_array:
        if network['channel'] <= 3:
            network['channel'] = 1
        elif 4 <= network['channel'] <= 8:
            network['channel'] = 6
        elif network['channel'] > 8:
            network['channel'] = 11
    return networks_array

def reject_max_RSSI(networks_array, max_rssi):
    all_networks_strong = []
    for network in networks_array:
        if abs(network['signal']) <= max_rssi:
            all_networks_strong.append(network)
    return all_networks_strong

def ban_channels(networks_array, max_bssid_per_chan):
        banned_channels = []
        nb_ssid_per_channel = dict()
        for channel in [1, 6, 11]:
            nb_ssid_per_channel[channel] = sum(n['channel'] == channel for n in networks_array )
            if nb_ssid_per_channel[channel] > max_bssid_per_chan:
                banned_channels.append(channel)
        return banned_channels, nb_ssid_per_channel

def reject_banned_networks(networks_array, blacklist):
    allowed_networks = []
    for allowed_network in (network for network in networks_array if network['channel'] not in blacklist):
        allowed_networks.append(allowed_network)
    return allowed_networks


def find_best_channel(interface, max_rssi, max_bssid_per_chan):

    #
    #Get list of networks
    #
    all_networks = scan(interface)

    #
    #Aggregate channels
    #
    all_networks = aggregate(all_networks)
    
    #If there is any free channel there is no goal in continuing.
    free_channel = check_free_channel(all_networks)
    if free_channel != None:
       return free_channel
    
    
    #
    #Rule out networks with |BSSID| > max_rssi
    #
    all_networks_strong = reject_max_RSSI(all_networks, max_rssi)

    #If there is any free channel there is no goal in continuing.
    free_channel = check_free_channel(all_networks_strong)
    if free_channel != None:
       return free_channel


    #
    #Rule out channels with more than max_bssid_per_chan networks
    #
    banned_channels, nb_ssid_per_channel = ban_channels(all_networks_strong, max_bssid_per_chan)
    allowed_networks = reject_banned_networks(all_networks_strong, banned_channels)

    #We cannot continue without any network so if every channel is too much congested we delete the most congested one
    if len(allowed_networks) == 0:
        most_congesionned_channel = max(zip(nb_ssid_per_channel.values(), nb_ssid_per_channel.keys()))[1]
        banned_channels = [most_congesionned_channel]
        allowed_networks = reject_banned_networks(all_networks_strong, banned_channels)
    

    #
    # Check which are the interesting channels (channels 1, 6, 11 minus the banned ones)
    #
    interesting_channels = list(set([1,6,11]) - set(banned_channels))
    if len(interesting_channels) == 1:
       return interesting_channels[0]


    #
    # Calculate the average RSSI per channel
    #
    averages = dict()
    for channel in interesting_channels:
        if(nb_ssid_per_channel[channel] != 0):
            avg = mean(abs(network['signal']) for network in allowed_networks if network['channel'] == channel )
        else:
            avg=float("inf")
        averages[channel] = avg
    best_channel = max(zip(averages.values(), averages.keys()))[1]
    return best_channel


if __name__ == "__main__":
    if len(sys.argv) < 4:
        print("usage : " + sys.argv[0] + " [interface] [max_rssi] [max_bssid_per_chan]")
        sys.exit(1)
    interface = sys.argv[1]
    max_rssi = float(sys.argv[2])
    max_bssid_per_chan = int(sys.argv[3])
    
    best_channel = find_best_channel(interface, max_rssi, max_bssid_per_chan)
    print("best_channel =",best_channel)
    










    

    

