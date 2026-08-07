#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
TLS_DIR="$PROJECT_ROOT/deploy/compose/tls"
CA_KEY="$TLS_DIR/ca.key"
CA_CERT="$TLS_DIR/ca.crt"
SERVER_KEY="$TLS_DIR/server.key"
SERVER_CERT="$TLS_DIR/server.crt"
SERVER_CSR="$TLS_DIR/server.csr"
SERVER_EXT="$TLS_DIR/server.ext"

mkdir -p "$TLS_DIR"
chmod 700 "$TLS_DIR"

required=("$CA_KEY" "$CA_CERT" "$SERVER_KEY" "$SERVER_CERT")
existing=0
for path in "${required[@]}"; do
    [[ -f "$path" ]] && existing=$((existing + 1))
done

if (( existing != 0 && existing != ${#required[@]} )); then
    echo "ERROR: Partial TLS material exists in $TLS_DIR."
    echo "Refusing to overwrite or mix certificate generations."
    exit 1
fi

if (( existing == 0 )); then
    openssl genpkey \
        -algorithm RSA \
        -pkeyopt rsa_keygen_bits:3072 \
        -out "$CA_KEY"

    openssl req \
        -x509 \
        -new \
        -sha256 \
        -key "$CA_KEY" \
        -days 3650 \
        -subj "/CN=SmartFactory DSS Local Development Root CA/O=SmartFactory DSS Prototype" \
        -addext "basicConstraints=critical,CA:TRUE,pathlen:0" \
        -addext "keyUsage=critical,keyCertSign,cRLSign" \
        -addext "subjectKeyIdentifier=hash" \
        -out "$CA_CERT"

    openssl genpkey \
        -algorithm RSA \
        -pkeyopt rsa_keygen_bits:2048 \
        -out "$SERVER_KEY"

    openssl req \
        -new \
        -sha256 \
        -key "$SERVER_KEY" \
        -subj "/CN=localhost/O=SmartFactory DSS Prototype" \
        -out "$SERVER_CSR"

    cat > "$SERVER_EXT" <<'EOF'
basicConstraints=critical,CA:FALSE
keyUsage=critical,digitalSignature,keyEncipherment
extendedKeyUsage=serverAuth
subjectAltName=DNS:localhost,IP:127.0.0.1
authorityKeyIdentifier=keyid,issuer
subjectKeyIdentifier=hash
EOF

    openssl x509 \
        -req \
        -sha256 \
        -in "$SERVER_CSR" \
        -CA "$CA_CERT" \
        -CAkey "$CA_KEY" \
        -CAcreateserial \
        -days 397 \
        -extfile "$SERVER_EXT" \
        -out "$SERVER_CERT"

    rm -f \
        "$SERVER_CSR" \
        "$SERVER_EXT" \
        "$TLS_DIR/ca.srl"
fi

chmod 600 "$CA_KEY" "$SERVER_KEY"
chmod 644 "$CA_CERT" "$SERVER_CERT"

openssl verify \
    -CAfile "$CA_CERT" \
    "$SERVER_CERT"

openssl x509 \
    -in "$SERVER_CERT" \
    -noout \
    -checkend 2592000

openssl x509 \
    -in "$SERVER_CERT" \
    -noout \
    -ext subjectAltName |
    grep -q 'DNS:localhost'

openssl x509 \
    -in "$SERVER_CERT" \
    -noout \
    -ext subjectAltName |
    grep -q 'IP Address:127.0.0.1'

echo "SMARTFACTORY LOCAL TLS GENERATION PASSED"
echo "CA private key remains only in the ignored Ubuntu TLS directory."
echo "Server certificate covers localhost and 127.0.0.1."
