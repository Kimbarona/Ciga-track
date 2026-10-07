import StaffLayout from '@/layouts/staff-layout';
import type { AppLayoutProps } from '@/types';

/**
 * The starter kit's settings pages use AppLayout; render them inside the staff shell
 * so the whole app shares one navigation.
 */
export default ({ children }: AppLayoutProps) => (
    <StaffLayout>{children}</StaffLayout>
);
