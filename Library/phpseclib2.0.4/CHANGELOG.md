# Changelog

## 2.0.3 - 2016-08-18

- BigInteger/RSA: don't compare openssl versions > 1.0 (#946)
- RSA: don't attempt to use the CRT when zero value components exist (#980)
- RSA: zero salt length RSA signatures don't work (#1002)
- ASN1: fix PHP Warning on PHP 7.1 (#1013)
- X509: set parameter fields to null for CSR's / RSA (#914)
- CRL optimizations (#1000)
- SSH2: fix "Expected SSH_FXP_STATUS or ..." error (#999)
- SSH2: use stream_get_* instead of fread() / fgets() (#967)
- SFTP: make symlinks support relative target's (#1004)
- SFTP: fix sending stream resulting in zero byte file (#995)

## 2.0.2 - 2016-06-04

- All Ciphers: fix issue with CBC mode / OpenSSL / continuous buffers / decryption (#938)
- Random: fix issues with serialize() (#932)
- RC2: fix issue with decrypting
- RC4: fix issue with key not being truncated correctly
- SFTP: nlist() on a non-existant directory resulted in error
- SFTP: add is_writable, is_writeable, is_readable
- X509: add IPv6 support for subjectaltname extension (#936)

## 2.0.1 - 2016-01-18

- RSA: fix regression in PSS mode ([#769](https://github.com/phpseclib/phpseclib/pull/769))
- RSA: fix issue loading PKCS8 specific keys ([#861](https://github.com/phpseclib/phpseclib/pull/861))
- X509: add getOID() method ([#789](https://github.com/phpseclib/phpseclib/pull/789))
- X509: improve base64-encoded detection rules ([#855](https://github.com/phpseclib/phpseclib/pull/855))
- SFTP: fix quirky behavior with put() ([#830](https://github.com/phpseclib/phpseclib/pull/830))
- SFTP: fix E_NOTICE ([#883](https://github.com/phpseclib/phpseclib/pull/883))
- SFTP/Stream: fix issue with filenames with hashes ([#901](https://github.com/phpseclib/phpseclib/pull/901))
- SSH2: add isAuthenticated() method ([#897](https://github.com/phpseclib/phpseclib/pull/897))
- SSH/Agent: fix possible PHP warning ([#923](https://github.com/phpseclib/phpseclib/issues/923))
- BigInteger: add __debugInfo() magic method ([#881](https://github.com/phpseclib/phpseclib/pull/881))
- BigInteger: fix issue with doing bitwise not on 0
- add getBlockLength() method to symmetric ciphers

## 2.0.0 - 2015-08-04

- Classes were renamed and namespaced ([#243](https://github.com/phpseclib/phpseclib/issues/243))
- The use of an autoloader is now required (e.g. Composer)

## 1.0.3 - 2016-08-18

- BigInteger/RSA: don't compare openssl versions > 1.0 (#946)
- RSA: don't attempt to use the CRT when zero value components exist (#980)
- RSA: zero salt length RSA signatures don't work (#1002)
- ASN1: fix PHP Warning on PHP 7.1 (#1013)
- X509: set parameter fields to null for CSR's / RSA (#914)
- CRL optimizations (#1000)
- SSH2: fix "Expected SSH_FXP_STATUS or ..." error (#999)
- SFTP: make symlinks support relative target's (#1004)
- SFTP: fix sending stream resulting in zero byte file (#995)

## 1.0.2 - 2016-05-07

- All Ciphers: fix issue with CBC mode / OpenSSL / continuous buffers / decryption (#938)
- Random: fix issues with serialize() (#932)
- RC2: fix issue with decrypting
- RC4: fix issue with key not being truncated correctly
- SFTP: nlist() on a non-existent directory resulted in error
- SFTP: add is_writable, is_writeable, is_readable
- RSA: fix PHP4 compatibility issue

## 1.0.1 - 2016-01-18

- RSA: fix regression in PSS mode ([#769](https://github.com/phpseclib/phpseclib/pull/769))
- RSA: fix issue loading PKCS8 specific keys ([#861](https://github.com/phpseclib/phpseclib/pull/861))
- X509: add getOID() method ([#789](https://github.com/phpseclib/phpseclib/pull/789))
- X509: improve base64-encoded detection rules ([#855](https://github.com/phpseclib/phpseclib/pull/855))
- SFTP: fix quirky behavior with put() ([#830](https://github.com/phpseclib/phpseclib/pull/830))
- SFTP: fix E_NOTICE ([#883](https://github.com/phpseclib/phpseclib/pull/883))
- SFTP/Stream: fix issue with filenames with hashes ([#901](https://github.com/phpseclib/phpseclib/pull/901))
- SSH2: add isAuthenticated() method ([#897](https://github.com/phpseclib/phpseclib/pull/897))
- SSH/Agent: fix possible PHP warning ([#923](https://github.com/phpseclib/phpseclib/issues/923))
- BigInteger: add __debugInfo() magic method ([#881](https://github.com/phpseclib/phpseclib/pull/881))
- BigInteger: fix issue with doing bitwise not on 0
- add getBlockLength() method to symmetric ciphers

## 1.0.0 - 2015-08-02

- OpenSSL support for symmetric ciphers ([#507](https://github.com/phpseclib/phpseclib/pull/507))
- rewritten vt100 terminal emulator (File_ANSI) ([#689](https://github.com/phpseclib/phpseclib/pull/689))
- agent-forwarding support (System_SSH_Agent) ([#592](https://github.com/phpseclib/phpseclib/pull/592))
- Net_SSH2 improvements
 - diffie-hellman-group-exchange-sha1/sha256 support ([#714](https://github.com/phpseclib/phpseclib/pull/714))
 - window size handling updates ([#717](https://github.com/phpseclib/phpseclib/pull/717))
- Net_SFTP improvements
 - add callback support to put() ([#655](https://github.com/phpseclib/phpseclib/pull/655))
 - stat cache fixes ([#743](https://github.com/phpseclib/phpseclib/issues/743), [#730](https://github.com/phpseclib/phpseclib/issues/730), [#709](https://github.com/phpseclib/phpseclib/issues/709), [#726](https://github.com/phpseclib/phpseclib/issues/726))
- add "none" encryption mode to Crypt_RSA ([#692](https://github.com/phpseclib/phpseclib/pull/692))
- misc ASN.1 / X.509 parsing fixes ([#721](https://github.com/phpseclib/phpseclib/pull/721), [#627](https://github.com/phpseclib/phpseclib/pull/627))
- use a random serial number for new X509 certs ([#740](https://github.com/phpseclib/phpseclib/pull/740))
- add getPublicKeyFingerprint() to Crypt_RSA ([#677](https://github.com/phpseclib/phpseclib/pull/677))

## 0.3.10 - 2015-02-04

- simplify SSH2 window size handling ([#538](https://github.com/phpseclib/phpseclib/pull/538))
- slightly relax the conditions under which OpenSSL is used ([#598](https://github.com/phpseclib/phpseclib/pull/598))
- fix issue with empty constructed context-specific tags in ASN1 ([#606](https://github.com/phpseclib/phpseclib/pull/606))

## 0.3.9 - 2014-11-09

- PHP 5.6 improvements ([#482](https://github.com/phpseclib/phpseclib/pull/482), [#491](https://github.com/phpseclib/phpseclib/issues/491))

## 0.3.8 - 2014-09-12

- improve support for indef lengths in File_ASN1
- add hmac-sha2-256 support to Net_SSH2
- make it so negotiated algorithms can be seen before Net_SSH2 login
- add sha256-96 and sha512-96 to Crypt_Hash
- window size handling adjustments in Net_SSH2

## 0.3.7 - 2014-07-05

- auto-detect public vs private keys
- add file_exists, is_dir, is_file, readlink and symlink to Net_SFTP
- add support for recursive nlist and rawlist
- make it so nlist and rawlist can return pre-sorted output
- make it so callback functions can make exec() return early
- add signSPKAC and saveSPKAC methods to File_X509
- add support for PKCS8 keys in Crypt_RSA
- add pbkdf1 support to setPassword() in Crypt_Base
- add getWindowColumns, getWindowRows, setWindowColumns, setWindowRows to Net_SSH2
- add support for filenames with spaces in them to Net_SCP

## 0.3.6 - 2014-02-23

- add preliminary support for custom SSH subsystems
- add ssh-agent support

## 0.3.5 - 2013-07-11

- numerous SFTP changes:
 - chown
 - chgrp
 - truncate
 - improved file type detection
 - put() can write to te middle of a file
 - mkdir accepts the same parameters that PHP's mkdir does
 - the ability to upload/download 2GB files
- across-the-board speedups for the various encryption algorithms
- multi-factor authentication support for Net_SSH2
- a $callback parameter for Net_SSH2::exec
- new classes:
 - Net_SFTP_StreamWrapper
 - Net_SCP
 - Crypt_Twofish
 - Crypt_Blowfish

## 0.3.1 - 2012-11-20

- add Net_SSH2::enableQuietMode() for suppressing stderr
- add Crypt_RSA::__toString() and Crypt_RSA::getSize()
- fix problems with File_X509::validateDate(), File_X509::sign() and Crypt_RSA::verify()
- use OpenSSL to speed up modular exponention in Math_BigInteger
- improved timeout functionality in Net_SSH2
- add support for SFTPv2
- add support for CRLs in File_X509
- SSH-2.0-SSH doesn't implement hmac-*-96 correctly

## 0.3.0 - 2012-07-08

- add support for reuming Net_SFTP::put()
- add support for recursive deletes and recursive chmods to Net_SFTP
- add setTimeout() to Net_SSH2
- add support for PBKDF2 to the various Crypt_* classes via setPassword()
- add File_X509 and File_ASN1
- add the ability to decode various formats in Crypt_RSA
- make Net_SSH2::getServerPublicHostKey() return a printer-friendly version of the public key

## 0.2.2 - 2011-05-09

- CFB and OFB modes were added to all block ciphers
- support for interactive mode was added to Net_SSH2
- Net_SSH2 now has limited keyboard_interactive authentication support
- support was added for PuTTY formatted RSA private keys and XML formatted RSA private keys
- Crypt_RSA::loadKey() will now try all key types automatically
= add support for AES-128-CBC and DES-EDE3-CFB encrypted RSA private keys
- add Net_SFTP::stat(), Net_SFTP::lstat() and Net_SFTP::rawlist()
- logging was added to Net_SSH1
- the license was changed to the less restrictive MIT license
