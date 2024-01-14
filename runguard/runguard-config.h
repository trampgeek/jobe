/* Runguard config for use with CodeRunner. Includes all necessary
 * DOMJudge constants from config.h.
 * Assumes all tests will be done as the Linux user "coderunner".
 * It is assumed CHROOT will not be used so the CHROOT_PREFIX
 * is not meaningfully set.
 */

#ifndef _RUNGUARD_CONFIG_
#define _RUNGUARD_CONFIG_

#define DOMJUDGE_VERSION "3"
#define REVISION "3.3"

#define VALID_USERS "domjudge,jobe,jobe00,jobe01,jobe02,jobe03,jobe04,jobe05,jobe06,jobe07"

#define CHROOT_PREFIX "/var/www/jobe/chrootjail"

#endif /* _RUNGUARD_CONFIG_ */
