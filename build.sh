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
AUTO_COMMIT=1

MODE="tags"

# The mode is missing.
if [ "" != "$1" ]; then
  MODE="$1"
else
  echo "Mode is missing! [prod|**]"
  exit 1
fi

cd "${ROOT}/${TARGET}" && git pull

MODE_TARGET="${TARGET}"

# Non-stable mode is being used.
if [ "${MODE}" != "stable" ]; then
  MODE_TARGET="${TARGET}/${MODE}"
fi

# Disable auto-commit build.
if [ "0" == "$2" ]; then
  AUTO_COMMIT="$2"
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

/bin/cp -f "${ROOT}/.env" "${ROOT}/${BUILD}/.env"

touch "${ROOT}/${MODE_TARGET}/latest"

SNAPSHOT_VERSION=""

# create latest dev build
if [ "stable" != "${MODE}" ]; then
  VERSION=`git log --pretty="%H" -n1 HEAD`

  if [ ! -f "${ROOT}/${MODE_TARGET}/${VERSION}" -o "${VERSION}" != "`cat \"${ROOT}/${MODE_TARGET}/latest\"`" ]; then
    rm -rf "${ROOT}/${MODE_TARGET}/download/"
    mkdir -p "${ROOT}/${MODE_TARGET}/download/${VERSION}/"
    ${COMPOSER} install -q --no-dev && \
    bin/compile ${MODE} ${VERSION} && \
    touch --date="`git log -n1 --pretty=%ci HEAD`" "builds/${BUILD_FILE}" && \
    git reset --hard -q ${VERSION} && \
    echo "${VERSION}" > "${ROOT}/${MODE_TARGET}/latest_new" && \
    mv "builds/${BUILD_FILE}" "${ROOT}/${MODE_TARGET}/download/${VERSION}/${BUILD_FILE}" && \
    mv "${ROOT}/${MODE_TARGET}/latest_new" "${ROOT}/${MODE_TARGET}/latest"

    sha256sum "${ROOT}/${MODE_TARGET}/download/${VERSION}/${BUILD_FILE}" >> "${ROOT}/${MODE_TARGET}/download/${VERSION}/sha256"

    LATEST_VERSION="${VERSION}"
    LATEST_BUILD="${VERSION}/${BUILD_FILE}"
  fi
fi

# create tagged releases
if [ "prod" == "${MODE}" ]; then
  for VERSION in `git tag`; do
    if [ ! -f "${ROOT}/${MODE_TARGET}/download/${VERSION}/${BUILD_FILE}" ]; then
      mkdir -p "${ROOT}/${MODE_TARGET}/download/${VERSION}/"
      git checkout ${VERSION} -q && \
      ${COMPOSER} install -q --no-dev && \
      bin/compile ${MODE}  ${VERSION} && \
      touch --date="`git log -n1 --pretty=%ci ${VERSION}`" "builds/${BUILD_FILE}" && \
      git reset --hard -q ${VERSION} && \
      mv "builds/${BUILD_FILE}" "${ROOT}/${MODE_TARGET}/download/${VERSION}/${BUILD_FILE}"

      sha256sum "${ROOT}/${MODE_TARGET}/download/${VERSION}/${BUILD_FILE}" >> "${ROOT}/${MODE_TARGET}/download/${VERSION}/sha256"

      echo "${MODE_TARGET}/download/${VERSION}/${BUILD_FILE} has been built"
    fi
  done

  LATEST_VERSION=$(ls "${ROOT}/${MODE_TARGET}/download" | grep -E '^[0-9.]+$' | sort -r -V | head -1)
  LATEST_BUILD="${LATEST_VERSION}/${BUILD_FILE}"
fi

echo "${LATEST_VERSION}" > "${ROOT}/${MODE_TARGET}/latest"

versions_contents="{\n"

while IFS= read -r -d "|" VERSION; do
  versions_contents="${versions_contents}  \"${VERSION}\": {\"path\": \"/download/${VERSION}/mysql-helper\"},\n"
done <<< $(find "${ROOT}/${MODE_TARGET}/download" -maxdepth 1 -mindepth 1 -printf '%f|')

versions_contents="${versions_contents}}"

echo -e "${versions_contents}" > "${ROOT}/${MODE_TARGET}/versions"
sed -i '1h;1!H;$!d;${s/.*//;x};s/\(.*\),/\1 /' "${ROOT}/${MODE_TARGET}/versions"

if [ "${AUTO_COMMIT}" == "1" ]; then
  cd "${ROOT}/${TARGET}" && git add . && git commit -m "Added compilied ${VERSION} binary" && git push
  cd "${ROOT}" && git add "public-web" && git commit -m "Update ${TARGET} with latest commit" && git push
fi
