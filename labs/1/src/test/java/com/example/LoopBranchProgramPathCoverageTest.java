package com.example;

import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.assertEquals;

class LoopBranchProgramPathCoverageTest {

    private final LoopBranchProgram program = new LoopBranchProgram();

    @Test
    void shouldCoverPathWithoutLoopIteration() {
        int result = program.classifyAndScore(new int[]{});
        assertEquals(0, result);
    }

    @Test
    void shouldCoverPositiveThenFinalTruePath() {
        int result = program.classifyAndScore(new int[]{2, 4, 6});
        assertEquals(1, result);
    }

    @Test
    void shouldCoverZeroAndNegativeThenFinalFalsePath() {
        int result = program.classifyAndScore(new int[]{0, -3, 0});
        assertEquals(0, result);
    }

    @Test
    void shouldCoverMixedBranchesWithFinalTruePath() {
        int result = program.classifyAndScore(new int[]{-1, 0, 5, 7, -2, 9});
        assertEquals(1, result);
    }
}
