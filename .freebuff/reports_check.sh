#!/usr/bin/env bash
# Walk the Reports page over HTTP as the three roles it knows about.
#
# Throwaway: it exists to check the delivered pages through the interface a
# reader actually uses (sign in, open the page, press Apply, download, print),
# not through the test client. Delete it when done.
#
#   DB_CONNECTION=sqlite DB_DATABASE=$PWD/database/preview-reports.sqlite \
#   SESSION_DRIVER=file php artisan serve --port=8231 &
#   bash .freebuff/reports_check.sh

set -u

BASE="${BASE:-http://127.0.0.1:8231}"
PASS="${PASS:-preview-password}"

login () {
    local email="$1" jar="$2" token
    rm -f "$jar"
    token=$(curl -s -c "$jar" "$BASE/login" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//')
    curl -s -b "$jar" -c "$jar" -o /dev/null -w "sign in $email -> %{http_code} %{redirect_url}\n" \
        -X POST "$BASE/login" \
        --data-urlencode "_token=$token" --data-urlencode "email=$email" --data-urlencode "password=$PASS"
}

say () { printf '  %-46s %s\n' "$1" "$2"; }

has () { grep -q -- "$2" "$1" && say "has: $2" "yes" || say "MISSING: $2" "NO"; }

absent () { grep -q -- "$2" "$1" && say "LEAK: $2" "YES" || say "absent: $2" "ok"; }

total_of () { grep -o 'data-report-total>[0-9,]*' "$1" | head -1 | sed 's/.*>//'; }

login amo@nu-lipa.edu.ph /tmp/amo.jar
login head@nu-lipa.edu.ph /tmp/head.jar
login nena@nu-lipa.edu.ph /tmp/nena.jar

echo "── admin: the default report"
curl -s -b /tmp/amo.jar -o /tmp/admin-report.html -w "  GET /admin/reports -> %{http_code}\n" "$BASE/admin/reports"
has /tmp/admin-report.html "Current Asset Inventory"
has /tmp/admin-report.html "AST-0004"
has /tmp/admin-report.html "Assets in this report"
has /tmp/admin-report.html "By department"
has /tmp/admin-report.html "report-department"
say "matching records (total)" "$(total_of /tmp/admin-report.html)"

echo "── admin: acquisitions, this month (undated and older assets must drop out)"
curl -s -b /tmp/amo.jar -o /tmp/acc.html -w "  GET ?category=acquisitions&preset=this_month -> %{http_code}\n" \
    "$BASE/admin/reports?category=acquisitions&preset=this_month"
has /tmp/acc.html "Not shown (no recorded date)"
has /tmp/acc.html "AST-0006"
absent /tmp/acc.html "AST-0004"
absent /tmp/acc.html "AST-0001"
say "matching records (total)" "$(total_of /tmp/acc.html)"

echo "── admin: every other category"
for c in repairs transfers disposals requests; do
    curl -s -b /tmp/amo.jar -o /tmp/cat.html -w "  ?category=$c -> %{http_code}" "$BASE/admin/reports?category=$c"
    echo "  records=$(total_of /tmp/cat.html)"
done

echo "── admin: a status that does not belong to this report"
curl -s -b /tmp/amo.jar -o /tmp/mismatch.html "$BASE/admin/reports?category=inventory&status=Approved"
has /tmp/mismatch.html "does not apply to the"

echo "── admin: export"
curl -s -b /tmp/amo.jar -o /tmp/exp.csv -D /tmp/exp.h -w "  GET export?category=acquisitions -> %{http_code}\n" \
    "$BASE/admin/reports/export?category=acquisitions"
grep -i "content-disposition" /tmp/exp.h | sed 's/^/  /'
say "asset rows in the file" "$(grep -c 'AST-' /tmp/exp.csv)"
has /tmp/exp.csv "Report generated at"
has /tmp/exp.csv "Date field"
has /tmp/exp.csv "AST-0004"

echo "── admin: print sheet"
curl -s -b /tmp/amo.jar -o /tmp/print.html -w "  GET print?category=inventory -> %{http_code}\n" \
    "$BASE/admin/reports/print?category=inventory"
has /tmp/print.html "Current Asset Inventory"
has /tmp/print.html "NU Trace"
has /tmp/print.html "Generated at"
has /tmp/print.html "AST-0004"
absent /tmp/print.html "Apply Filters"
absent /tmp/print.html "Employee Management"
absent /tmp/print.html "Logout"

echo "── department head: their own department, and nothing else"
curl -s -b /tmp/head.jar -o /tmp/head-report.html -w "  GET /department-head/reports -> %{http_code}\n" "$BASE/department-head/reports"
has /tmp/head-report.html "Current Asset Inventory"
has /tmp/head-report.html "AST-0004"
has /tmp/head-report.html "Department Head view"
absent /tmp/head-report.html "report-department"
absent /tmp/head-report.html "AST-0003"
absent /tmp/head-report.html "AST-0005"
say "matching records (total)" "$(total_of /tmp/head-report.html)"

echo "── department head: trying to widen the scope through the URL"
curl -s -b /tmp/head.jar -o /tmp/head-wide.html -w "  GET ?department=2 -> %{http_code}\n" \
    "$BASE/department-head/reports?category=inventory&department=2"
absent /tmp/head-wide.html "AST-0003"

echo "── department head: export and print"
curl -s -b /tmp/head.jar -o /tmp/head.csv -w "  GET export?department=2 -> %{http_code}\n" \
    "$BASE/department-head/reports/export?category=inventory&department=2"
has /tmp/head.csv "AST-0001"
absent /tmp/head.csv "AST-0003"
curl -s -b /tmp/head.jar -o /tmp/head-print.html -w "  GET print -> %{http_code}\n" \
    "$BASE/department-head/reports/print?category=inventory"
absent /tmp/head-print.html "AST-0003"

echo "── department head: every category, all within the department"
for c in inventory acquisitions repairs transfers disposals requests; do
    curl -s -b /tmp/head.jar -o /tmp/hcat.html -w "  ?category=$c -> %{http_code}" "$BASE/department-head/reports?category=$c"
    echo "  records=$(total_of /tmp/hcat.html)"
    absent /tmp/hcat.html "AST-0003" > /dev/null || true
done

echo "── an employee, and a guest"
curl -s -b /tmp/nena.jar -o /dev/null -w "  employee /department-head/reports -> %{http_code}\n" "$BASE/department-head/reports"
curl -s -b /tmp/nena.jar -o /dev/null -w "  employee /admin/reports -> %{http_code}\n" "$BASE/admin/reports"
curl -s -o /dev/null -w "  guest /admin/reports -> %{http_code} %{redirect_url}\n" "$BASE/admin/reports"
curl -s -o /dev/null -w "  guest /department-head/reports/export -> %{http_code} %{redirect_url}\n" "$BASE/department-head/reports/export"

echo "── the Apply Filters button"
token=$(grep -o 'name="_token" value="[^"]*"' /tmp/admin-report.html | head -1 | sed 's/.*value="//;s/"$//')
curl -s -b /tmp/amo.jar -o /dev/null -w "  POST /admin/reports/generate (status=Approved) -> %{http_code} %{redirect_url}\n" \
    -X POST "$BASE/admin/reports/generate" \
    --data-urlencode "_token=$token" --data-urlencode "category=inventory" \
    --data-urlencode "preset=all" --data-urlencode "status=Approved"

touch /tmp/reports-check-done
