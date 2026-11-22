#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
if [ -z "$(printenv PAWNCC || true)" ]; then
  echo "Set PAWNCC to the official Pawn compiler path."
  exit 2
fi
mkdir -p logs
rm -f gamemodes/sunkora.amx filterscripts/other.amx
"$PAWNCC" gamemodes/sunkora.pwn -Z+ -ogamemodes/sunkora.amx 2>&1 | tee logs/compile-gamemode.log
test -s gamemodes/sunkora.amx
"$PAWNCC" filterscripts/other.pwn -Z+ -ofilterscripts/other.amx 2>&1 | tee logs/compile-filterscript.log
test -s filterscripts/other.amx
echo "SUNKORA: AMX build completed."
