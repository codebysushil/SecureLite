typedef struct sqlite3 sqlite3;

int sqlite3_open_v2(
    const char *filename,
    sqlite3 **ppDb,
    int flags,
    const char *zVfs
);

int sqlite3_close(sqlite3 *db);

const char *sqlite3_errmsg(sqlite3 *db);

int sqlite3_key(
    sqlite3 *db,
    const void *pKey,
    int nKey
);

int sqlite3_exec(
    sqlite3 *db,
    const char *sql,
    void *callback,
    void *arg,
    char **errmsg
);

const char *sqlite3_libversion(void);
