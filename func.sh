#!/usr/bin/env bash

ssh_random_string() {
	symbols=${1-'A-Za-z0-9'}

	length=40
	if [[ $2 =~ ^[0-9]+$ ]]; then
		length=$2
	fi

	if [[ "$OSTYPE" == "darwin"* ]]; then
		LC_ALL=C tr -dc "$symbols" < /dev/urandom | head -c "$length"
	else
		tr -dc "$symbols" < /dev/urandom | head -c "$length"
	fi
	echo
}

