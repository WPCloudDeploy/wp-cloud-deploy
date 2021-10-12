This v0 version is a test version to debug versioning...
it does not contain the VNSTAT command, otherwise it is the same as the V1 scripts:

after-server-create-run-commands.txt - the commands that will be sent to a server after its been provisioned.  It includes some tokens that will be replaced dynamically as the script is run.

digital-ocean-startup-run-commands.txt - not used in this app.

install-open-vpn.txt - script that will be executed to create the vpn server.  It is going to be called via a wget command from the after-server-create-run-commands.txt script.

params.sh - Tokens that will be used to pass parameters to install-open-vpn.txt script using linux EXPORT environmental variables.