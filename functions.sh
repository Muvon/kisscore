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

# Run checks using array checks
function run_checks {
  exit_code=0
  declare -A results

  for k in "${!checks[@]}"; do
    bash -c "${checks[$k]} &> /dev/null"
    [[ "$?" != "0" ]] && results["$k"]='\e[1;31mFail' && exit_code=1 || results["$k"]='\e[1;32mSuccess'
  done

  for k in "${!results[@]}"; do
    echo -e "\e[1;34m$k\e[0m - ${results[$k]}\e[0m"
  done

  exit $exit_code
}