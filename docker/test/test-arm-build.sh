#!/bin/bash
set -e

# register QEMU handlers for emulation (one-time)
docker run --rm --privileged multiarch/qemu-user-static --reset -p yes

# create and use a buildx builder (if you don't already have one)
if ! docker buildx inspect mybuilder > /dev/null 2>&1; then
    docker buildx create --name mybuilder --use
else
    docker buildx use mybuilder
fi
docker buildx inspect --bootstrap

# change to repo root
cd ../..

# build the alpine image for arm64 and amd64
# Note: We cannot use --load with multiple platforms. 
# We will build for arm64 specifically to test the emulation and load it.
echo "Building for linux/arm64..."
docker buildx build --platform linux/arm64 -f Dockerfile.alpine -t local-speedtest:alpine-arm64 --load .

# To build for both platforms, you typically need to push to a registry:
# docker buildx build --platform linux/amd64,linux/arm64 -f Dockerfile.alpine -t local-speedtest:alpine --push .
