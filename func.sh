#!/usr/bin/env bash
ssh_random_string() {
  symbols=${1-'A-Za-z0-9'}

  length=40
  if [[ $2 =~ ^[0-9]+$ ]]; then
    length=$2
  fi

  < /dev/urandom tr -dc ${symbols} | head -c${length} && echo
}
