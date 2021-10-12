#!/bin/bash

# var $option action 1 - add, 2 - remove
# var $unsanitized_client Tell me a name for the client certificate.
# var $protocol (1 - udp, 2 - tcp)
# var $port
# var dns ( 1 -  Current system resolvers, 2 - 1.1.1.1, 3 -  Google, 4 - OpenDNS, 5 -  Verisign")

export vpn_option=##OPTION##
export vpn_client="##CLIENT##"
export vpn_protocol=##PROTOCOL##
export vpn_port=##PORT##
export vpn_dns=##DNS##
export vpn_max_clients=##MAX_CLIENTS##

