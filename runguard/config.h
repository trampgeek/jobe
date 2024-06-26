/* Stub config.h file to replace etc/config.h, since that is only
 * generated after running configure, not at 'make dist' time.
 * We simply assume here that default headers are available.

COPIED from domjudge/sql/files/config.h.in but with dummy DOMJUDGE_VERSION

 */

#define DOMJUDGE_VERSION "@VERSION@"

/* Define to 1 if you have the <stdint.h> header file. */
#define HAVE_STDINT_H 1

/* Define to 1 if you have the <stdlib.h> header file. */
#define HAVE_STDLIB_H 1

/* Define to 1 if you have the <syslog.h> header file. */
#define HAVE_SYSLOG_H 1

/* Define to 1 if you have the <unistd.h> header file. */
#define HAVE_UNISTD_H 1

/* Define to 1 if you have the ANSI C header files. */
#define STDC_HEADERS 1

/* Include POSIX.1-2008 base specification */
#define _POSIX_C_SOURCE 200809L

/* Include SUSv2 (UNIX 98) extensions */
#define _XOPEN_SOURCE 500
