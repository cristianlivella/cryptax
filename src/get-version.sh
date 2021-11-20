git describe --tags > version.txt
git log -1 --format=%cd --date=short >> version.txt
