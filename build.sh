#!/bin/bash
#
cleanup() {
  echo "";
  echo "";
  exit
}

trap cleanup INT TERM

PWD=$(pwd)
ROOT="$( cd "$( dirname "$0" )" && cd ./ && pwd )"

# Standard config.
BUILD="build-src"
BUILD_FILE="fs-watcher"
REPO="git@github.com:hnhdigital-os/fs-watcher.git"
BRANCH="master"
COMPOSER="composer"
TARGET="public-web"

MODE="tags"

# The mode is missing.
if [ "" != "$1" ]; then
  MODE="$1"
else
  echo "Mode is missing! [prod|dev]"
  exit 1
fi

# Only prod or dev are valid options.
if [ "${MODE}" != "prod" ] &&  [ "${MODE}" != "dev" ]; then
  echo "Incorrect mode set! [prod|dev]"
  exit 1
fi

# Dev Mode is being used.
if [ "${MODE}" == "dev" ]; then
  TARGET="${TARGET}/${MODE}"
fi

# Branch.
if [ "" != "$3" ]; then
  BRANCH="$3"
fi

# Create or update build.
cd "${ROOT}"

if [ ! -d "${BUILD}/.git" ]; then
  git clone "${REPO}" "${BUILD}"
  cd "${ROOT}/${BUILD}"
  git checkout "$BRANCH"
else
  cd "${ROOT}/${BUILD}"
  git checkout "$BRANCH"
  git fetch -p -P
  git pull
fi

git submodule update --remote

# create latest dev build
if [ "dev" == "${MODE}" ]; then
  VERSION=`git log --pretty="%H" -n1 HEAD`

  if [ ! -f "${ROOT}/${TARGET}/${VERSION}" -o "${VERSION}" != "`cat \"${ROOT}/${TARGET}/snapshot\"`" ]; then
    rm -rf "${ROOT}/${TARGET}/download/snapshot/"
    mkdir -p "${ROOT}/${TARGET}/download/snapshot/"
    ${COMPOSER} install -q --no-dev && \
    bin/compile ${VERSION} && \
    touch --date="`git log -n1 --pretty=%ci HEAD`" "builds/${BUILD_FILE}" && \
    git reset --hard -q ${VERSION} && \
    echo "${VERSION}" > "${ROOT}/${TARGET}/snapshot_new" && \
    mv "builds/${BUILD_FILE}" "${ROOT}/${TARGET}/download/snapshot/${BUILD_FILE}-${VERSION}" && \
    mv "${ROOT}/${TARGET}/snapshot_new" "${ROOT}/${TARGET}/snapshot"
  fi
fi

# create tagged releases
if [ "prod" == "${MODE}" ]; then
  for VERSION in `git tag`; do
    if [ ! -f "${ROOT}/${TARGET}/download/${VERSION}/${BUILD_FILE}${BUILD_EXT}" ]; then
      mkdir -p "${ROOT}/${TARGET}/download/${VERSION}/"
      git checkout ${VERSION} -q && \
      ${COMPOSER} install -q --no-dev && \
      bin/compile ${VERSION} && \
      touch --date="`git log -n1 --pretty=%ci ${VERSION}`" "builds/${BUILD_FILE}" && \
      git reset --hard -q ${VERSION} && \
      mv "builds/${BUILD_FILE}" "${ROOT}/${TARGET}/download/${VERSION}/${BUILD_FILE}"
      echo "${TARGET}/download/${VERSION}/${BUILD_FILE} has been built"
    fi
  done
fi

SNAPSHOT_VERSION=$(head -c40 "${ROOT}/${TARGET}/snapshot")

STABLE_VERSION=$(ls "${ROOT}/${TARGET}/download" --ignore snapshot | grep -E '^[0-9.]+$' | sort -r -V | head -1)
STABLE_BUILD="${STABLE_VERSION}/${BUILD_FILE}${BUILD_EXT}"

if [ "" == "$STABLE_VERSION" ]; then
  STABLE_VERSION="${SNAPSHOT_VERSION}"
  STABLE_BUILD="snapshot/${BUILD_FILE}-${SNAPSHOT_VERSION}${BUILD_EXT}"
fi

read -r -d '' versions << EOM
{
  "stable": {"path": "/download/${STABLE_BUILD}", "version": "${STABLE_VERSION}", "min-php": 70000},
  "snapshot": {"path": "/download/snapshot/${BUILD_FILE}-${SNAPSHOT_VERSION}${BUILD_EXT}", "version": "${SNAPSHOT_VERSION}", "min-php": 70000}
}
EOM

echo "${STABLE_VERSION}" > "${ROOT}/${TARGET}/stable"
echo "${versions}" > "${ROOT}/${TARGET}/versions_new" && mv "${ROOT}/${TARGET}/versions_new" "${ROOT}/${TARGET}/versions"

# empty checksum
CHECKSUM_FILE="${ROOT}/${TARGET}/checksum"
> "${CHECKSUM_FILE}"

# Create checksum for each file
find "${ROOT}/${TARGET}" -name '*.phar' -print0 |
  while IFS= read -r -d $'\0' FILE; do
    sha256sum "$FILE" >> "${CHECKSUM_FILE}"
  done

sed -i s#${ROOT}/${TARGET}##g "${CHECKSUM_FILE}"

# Convert to JSON format
TEMP_CHECKSUM_FILE="${ROOT}/${TARGET}/temp_checksum"

printf "{\n" > "${TEMP_CHECKSUM_FILE}"
awk '{ print "\t\"" $2 "\": " "\"" $1 "\", " }' "${CHECKSUM_FILE}" >> "${TEMP_CHECKSUM_FILE}"
printf "}\n" >> "${TEMP_CHECKSUM_FILE}"
sed -i '1h;1!H;$!d;${s/.*//;x};s/\(.*\),/\1 /' "${TEMP_CHECKSUM_FILE}"

cat "${TEMP_CHECKSUM_FILE}" > "${CHECKSUM_FILE}"

unlink "${TEMP_CHECKSUM_FILE}"
