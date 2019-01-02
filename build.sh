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
  echo "Mode is missing! [stable|**]"
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

BUILDS_ROOT="${ROOT}/${BUILD}/builds"
PUBLIC_WEB_ROOT="${ROOT}/${MODE_TARGET}"

# Create or update build.
cd "${ROOT}"

if [ ! -d "${BUILD}/.git" ]; then
  git clone "${REPO}" "${BUILD}"
  cd "${ROOT}/${BUILD}"
  git checkout "$BRANCH"
else
  cd "${ROOT}/${BUILD}"
  rm -rf "${BUILDS_ROOT}"
  git checkout "$BRANCH"
  git fetch -p -P
  git pull
  git reset --hard
fi

git submodule update --remote

/bin/cp -f "${ROOT}/.env" "${ROOT}/${BUILD}/.env"

touch "${PUBLIC_WEB_ROOT}/latest"

# create latest dev build
if [ "stable" != "${MODE}" ]; then
  VERSION=`git log --pretty="%H" -n1 HEAD`

  if [ ! -f "${PUBLIC_WEB_ROOT}/${VERSION}" -o "${VERSION}" != "`cat \"${PUBLIC_WEB_ROOT}/latest\"`" ]; then
    rm -rf "${PUBLIC_WEB_ROOT}/download"
    mkdir -p "${PUBLIC_WEB_ROOT}/download/${VERSION}"

    ${COMPOSER} install -q --no-dev && \
    bin/compile ${MODE} ${VERSION} && \
    touch --date="`git log -n1 --pretty=%ci HEAD`" "${BUILDS_ROOT}/${BUILD_FILE}" && \
    git reset --hard -q ${VERSION} && \
    echo "${VERSION}" > "${PUBLIC_WEB_ROOT}/latest_new" && \
    mv "${BUILDS_ROOT}/${BUILD_FILE}" "${PUBLIC_WEB_ROOT}/download/${VERSION}/${BUILD_FILE}" && \
    mv "${PUBLIC_WEB_ROOT}/latest_new" "${PUBLIC_WEB_ROOT}/latest"

    sha256sum "${PUBLIC_WEB_ROOT}/download/${VERSION}/${BUILD_FILE}" >> "${PUBLIC_WEB_ROOT}/download/${VERSION}/sha256"

    LATEST_VERSION="${VERSION}"
    LATEST_BUILD="${VERSION}/${BUILD_FILE}"
  fi
fi

# create tagged releases
if [ "stable" == "${MODE}" ]; then
  for VERSION in `git tag`; do
    if [ ! -f "${PUBLIC_WEB_ROOT}/download/${VERSION}/${BUILD_FILE}" ]; then
      mkdir -p "${PUBLIC_WEB_ROOT}/download/${VERSION}/"

      git checkout ${VERSION} -q && \
      ${COMPOSER} install -q --no-dev && \
      bin/compile ${MODE}  ${VERSION} && \
      touch --date="`git log -n1 --pretty=%ci ${VERSION}`" "${BUILDS_ROOT}/${BUILD_FILE}" && \
      git reset --hard -q ${VERSION} && \
      mv "${BUILDS_ROOT}/${BUILD_FILE}" "${PUBLIC_WEB_ROOT}/download/${VERSION}/${BUILD_FILE}"

      sha256sum "${PUBLIC_WEB_ROOT}/download/${VERSION}/${BUILD_FILE}" >> "${PUBLIC_WEB_ROOT}/download/${VERSION}/sha256"

      echo "${MODE_TARGET}/download/${VERSION}/${BUILD_FILE} has been built"
    fi
  done

  LATEST_VERSION=$(ls "${PUBLIC_WEB_ROOT}/download" | grep -E '^[0-9.]+$' | sort -r -V | head -1)
  LATEST_BUILD="${LATEST_VERSION}/${BUILD_FILE}"
fi

echo "${LATEST_VERSION}" > "${PUBLIC_WEB_ROOT}/latest"

versions_contents="{\n"

while IFS= read -r -d "|" VERSION; do
  versions_contents="${versions_contents}  \"${VERSION}\": {\"path\": \"/download/${VERSION}/fs-watcher\"},\n"
done <<< $(find "${PUBLIC_WEB_ROOT}/download" -maxdepth 1 -mindepth 1 -printf '%f|')

versions_contents="${versions_contents}}"

echo -e "${versions_contents}" > "${PUBLIC_WEB_ROOT}/versions"
sed -i '1h;1!H;$!d;${s/.*//;x};s/\(.*\),/\1 /' "${PUBLIC_WEB_ROOT}/versions"

if [ "${AUTO_COMMIT}" == "1" ]; then
  cd "${ROOT}/${TARGET}" && git add . && git commit -m "Added compilied ${LATEST_VERSION} binary" && git push
  cd "${ROOT}" && git add "public-web" && git commit -m "Update ${TARGET} with latest commit" && git push
fi
