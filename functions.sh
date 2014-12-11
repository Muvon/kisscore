#!/bin/bash
# Random password generator
# symbols, length
function ssh_random_string {
  symbols='A-Za-z0-9'
  if [[ -z $1 ]]; then
    symbols=$1
  fi

  length=40
  if [[ $2 =~ ^[0-9]+$ ]]; then
    length=$2
  fi

  < /dev/urandom tr -dc ${symbols} | head -c${length} && echo
}

# Allocate port for service
function allocate_port {
  if [[ -z $1 ]]; then
    echo 'Usage: '$0' name'
    exit 1
  fi
  [[ ! -f ~/ports ]] && touch ~/ports

  start=33000
  max=33000
  for line in `cat ~/ports`; do
    port=${line##*:}
    if (( $max < $port )); then
      max=$port
    fi
  done
  port=$(( $max + 1 ))
  
  line=$(cat ~/ports | grep -E ^$1\:[0-9]+$)
  if [[ -z $line ]]; then
    echo "$1:$port" >> ~/ports
    cat ~/ports | sort -h > ~/ports.tmp
    mv ~/ports.tmp ~/ports
  fi
  echo $port
}
