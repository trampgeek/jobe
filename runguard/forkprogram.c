// Tests if runguard kills child processes on normal exit.

#include <linux/unistd.h>
#include <unistd.h>
#include <stdio.h>
int main() {
    int successes = 0, failures = 0;
    for (int i = 0; i < 1000; i++) {
        int pid = fork();
        if (pid == -1) {
            failures += 1;
        }
        else if (pid == 0) {
            while (1) {};  // Child loops
        }
        else {
            successes += 1;
        }
    }
    printf("%d forks succeeded, %d failed\n", successes, failures);
}
