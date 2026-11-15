"""Read-only FTPS connection inventory. Receives credentials over stdin from MSI DPAPI wrapper."""
import ftplib
import json
import ssl
import sys

PATHS = ("/", "/app/", "/public_html/")

def main():
    credentials = json.loads(sys.stdin.readline())
    context = ssl.create_default_context()
    context.minimum_version = ssl.TLSVersion.TLSv1_2
    results = []
    with ftplib.FTP_TLS(context=context, timeout=15) as ftp:
        ftp.connect(credentials["host"], int(credentials["port"]), timeout=15)
        ftp.login(credentials["username"], credentials["password"])
        ftp.prot_p()
        for path in PATHS:
            try:
                entries = ftp.nlst(path)
                results.append({"path": path, "state": "OK", "entry_count": len(entries)})
            except ftplib.error_perm as exc:
                results.append({"path": path, "state": "UNAVAILABLE", "ftp_code": str(exc)[:3]})
    print(json.dumps({"connection": "TLS_PROTECTED", "only_list_operations": True,
                      "files_downloaded": False, "production_modified": False,
                      "credentials_logged": False, "results": results}))

if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        code = str(exc)[:3] if isinstance(exc, ftplib.all_errors) and str(exc)[:3].isdigit() else ""
        print(json.dumps({"state": "FAILED", "reason": type(exc).__name__,
                          "ftp_code": code, "production_modified": False,
                          "credentials_logged": False}))
        raise SystemExit(1)
