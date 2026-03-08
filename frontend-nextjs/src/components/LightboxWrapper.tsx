'use client';

import { useState } from 'react';
import Lightbox from 'yet-another-react-lightbox';
import 'yet-another-react-lightbox/styles.css';

interface LightboxImage {
  src: string;
  alt?: string;
}

interface LightboxWrapperProps {
  images: LightboxImage[];
  children: (openLightbox: (index: number) => void) => React.ReactNode;
}

export function LightboxWrapper({ images, children }: LightboxWrapperProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [photoIndex, setPhotoIndex] = useState(0);

  // Only show lightbox if there are images
  const validImages = images.filter((img) => img.src);

  if (validImages.length === 0) {
    return <>{children(() => {})}</>;
  }

  const openLightbox = (index: number) => {
    setPhotoIndex(index);
    setIsOpen(true);
  };

  return (
    <>
      {children(openLightbox)}
      <Lightbox
        open={isOpen}
        close={() => setIsOpen(false)}
        index={photoIndex}
        slides={validImages}
        styles={{
          container: {
            backgroundColor: 'rgba(0, 0, 0, 0.95)',
          },
        }}
        carousel={{ preload: 2 }}
        animation={{ fade: 250, swipe: 250 }}
        controller={{ closeOnBackdropClick: true }}
      />
    </>
  );
}

// Simple hook for single image lightbox
export function useLightbox() {
  const [isOpen, setIsOpen] = useState(false);
  const [photoIndex, setPhotoIndex] = useState(0);

  const openLightbox = (index: number = 0) => {
    setPhotoIndex(index);
    setIsOpen(true);
  };

  const closeLightbox = () => {
    setIsOpen(false);
  };

  return {
    isOpen,
    photoIndex,
    openLightbox,
    closeLightbox,
  };
}

